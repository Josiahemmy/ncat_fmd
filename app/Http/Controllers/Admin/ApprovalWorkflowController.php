<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLevel;
use App\Models\ApprovalWorkflow;
use App\Services\Documents\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration screen for the approval workflow (spec §12.1). The whole
 * ordered level list is submitted at once, which makes add, rename, remove and
 * reorder a single atomic save and keeps sequence numbers contiguous.
 *
 * Editing levels never touches documents already in flight: those carry their
 * own snapshot in `requisition_approvals`.
 */
class ApprovalWorkflowController extends Controller
{
    public function __construct(protected ApprovalService $approvals)
    {
    }

    public function index(): Response
    {
        $workflow = $this->workflow();

        return Inertia::render('Admin/Approvals/Index', [
            'workflow' => [
                'id' => $workflow->id,
                'document_type' => $workflow->document_type,
                'name' => $workflow->name,
                'is_active' => $workflow->is_active,
            ],
            'levels' => $workflow->levels()->get()->map(fn (ApprovalLevel $l) => [
                'id' => $l->id,
                'sequence' => $l->sequence,
                'name' => $l->name,
                'binding_type' => $l->bindingType(),
                'binding_value' => $l->bindingLabel(),
                'is_active' => $l->is_active,
            ]),
            'permissions' => $this->approvals->bindablePermissions(),
            'roles' => \Spatie\Permission\Models\Role::orderBy('name')->pluck('name'),
            'inFlight' => $this->approvals->inFlightCount(),
        ]);
    }

    public function update(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'levels' => ['required', 'array', 'min:1'],
            'levels.*.id' => ['nullable', 'integer', Rule::exists('approval_levels', 'id')->where('approval_workflow_id', $workflow->id)],
            'levels.*.name' => ['required', 'string', 'max:255'],
            'levels.*.binding_type' => ['required', Rule::in(['permission', 'role'])],
            'levels.*.binding_value' => ['required', 'string', 'max:255'],
            'levels.*.is_active' => ['boolean'],
        ]);

        // `validated()` does not preserve element order when an earlier element
        // omits a nullable key (a new level has no id), and order IS the
        // configuration here. Sorting by the client's index restores it.
        $levels = $data['levels'];
        ksort($levels);

        // The binding target has to exist, otherwise a level would be
        // unsatisfiable and its documents would stall.
        foreach ($levels as $i => $level) {
            $table = $level['binding_type'] === 'role' ? 'roles' : 'permissions';

            if (! DB::table($table)->where('name', $level['binding_value'])->where('guard_name', 'web')->exists()) {
                return back()->withErrors([
                    "levels.{$i}.binding_value" => "That {$level['binding_type']} does not exist.",
                ]);
            }
        }

        if (! collect($levels)->contains(fn ($l) => ($l['is_active'] ?? true))) {
            return back()->withErrors(['levels' => 'At least one level must stay active.']);
        }

        DB::transaction(function () use ($levels, $workflow) {
            $kept = [];

            foreach (array_values($levels) as $i => $level) {
                $attributes = [
                    'approval_workflow_id' => $workflow->id,
                    'sequence' => $i + 1,
                    'name' => $level['name'],
                    'permission_name' => $level['binding_type'] === 'permission' ? $level['binding_value'] : null,
                    'role_name' => $level['binding_type'] === 'role' ? $level['binding_value'] : null,
                    'is_active' => $level['is_active'] ?? true,
                ];

                $model = ! empty($level['id'])
                    ? tap(ApprovalLevel::findOrFail($level['id']))->update($attributes)
                    : ApprovalLevel::create($attributes);

                $kept[] = $model->id;
            }

            // Levels dropped from the list go away. In-flight requisitions are
            // unaffected: their snapshot holds the level name and binding, and
            // the reference column is nulled rather than cascading.
            $workflow->levels()->whereKeyNot($kept)->delete();
        });

        activity('approval_workflow')->causedBy($request->user())->performedOn($workflow)
            ->event('updated')->log('Updated the requisition approval levels');

        return back()->with('success', 'Approval workflow saved.');
    }

    protected function workflow(): ApprovalWorkflow
    {
        return ApprovalWorkflow::firstOrCreate(
            ['document_type' => ApprovalService::DOCUMENT_TYPE],
            ['name' => 'Requisition approval', 'is_active' => true],
        );
    }
}
