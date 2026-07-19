<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\Part;
use App\Models\PartSerial;
use App\Models\Requisition;
use App\Models\WorkOrder;
use App\Services\Documents\DocumentNumberService;
use App\Services\Stock\SerialStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RequisitionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'aircraft' => $request->string('aircraft')->toString(),
        ];

        $rows = Requisition::query()
            ->with(['aircraft:id,registration', 'workOrder:id,wo_ref'])
            ->when($filters['search'], fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('requisition_no', 'like', "%{$v}%")
                ->orWhere('full_description', 'like', "%{$v}%")
                ->orWhere('part_no', 'like', "%{$v}%")))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['aircraft'], fn ($q, $v) => $q->where('aircraft_id', $v))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Requisition $r) => $this->row($r));

        return Inertia::render('Requisitions/Index', [
            'requisitions' => $rows,
            'aircraft' => Aircraft::orderBy('registration')->get(['id', 'registration']),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Requisitions/Create', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $data['requisition_no'] = app(DocumentNumberService::class)->reserve('requisition');
        $data['requested_by_user_id'] = $request->user()->id;
        $data['status'] = $request->input('submit') ? 'submitted' : 'draft';

        $requisition = Requisition::create($data);

        activity('requisition')->causedBy($request->user())->performedOn($requisition)
            ->event('created')->log("Raised requisition {$requisition->requisition_no}");

        return redirect()->route('requisitions.show', $requisition)
            ->with('success', "Requisition {$requisition->requisition_no} saved.");
    }

    public function show(Requisition $requisition): Response
    {
        $requisition->load([
            'aircraft.aircraftType', 'part', 'workOrder:id,wo_ref',
            'requestedBy:id,name', 'approvedBy:id,name', 'removedSerial',
        ]);

        return Inertia::render('Requisitions/Show', [
            'requisition' => $this->fullRow($requisition),
            'flow' => [
                'canApprove' => $requisition->status === 'submitted',
                'canRemoval' => in_array($requisition->status, ['issued', 'closed'], true),
            ],
        ]);
    }

    public function update(Request $request, Requisition $requisition): RedirectResponse
    {
        abort_unless(in_array($requisition->status, ['draft', 'rejected'], true), 422, 'Only draft requisitions can be edited.');

        $requisition->update($this->validated($request));

        if ($request->input('submit')) {
            $requisition->update(['status' => 'submitted']);
        }

        return redirect()->route('requisitions.show', $requisition)->with('success', 'Requisition updated.');
    }

    public function submit(Request $request, Requisition $requisition): RedirectResponse
    {
        abort_unless($requisition->status === 'draft', 422, 'Only a draft can be submitted.');
        $requisition->update(['status' => 'submitted']);

        return back()->with('success', "Requisition {$requisition->requisition_no} submitted for approval.");
    }

    public function approve(Request $request, Requisition $requisition): RedirectResponse
    {
        abort_unless($requisition->status === 'submitted', 422, 'Only a submitted requisition can be approved.');

        // Segregation of duties: an approver may not approve their own requisition.
        if ($requisition->requested_by_user_id === $request->user()->id) {
            throw ValidationException::withMessages([
                'approval' => 'You cannot approve a requisition you raised.',
            ]);
        }

        $requisition->update([
            'status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
            'approval_remarks' => $request->string('remarks')->toString() ?: null,
        ]);

        activity('requisition')->causedBy($request->user())->performedOn($requisition)
            ->event('approved')->log("Approved requisition {$requisition->requisition_no}");

        return back()->with('success', "Requisition {$requisition->requisition_no} approved.");
    }

    public function reject(Request $request, Requisition $requisition): RedirectResponse
    {
        abort_unless($requisition->status === 'submitted', 422, 'Only a submitted requisition can be rejected.');

        $data = $request->validate(['remarks' => ['required', 'string', 'max:1000']]);

        $requisition->update([
            'status' => 'rejected',
            'approved_by_user_id' => $request->user()->id,
            'rejected_at' => now(),
            'approval_remarks' => $data['remarks'],
        ]);

        activity('requisition')->causedBy($request->user())->performedOn($requisition)
            ->event('rejected')->log("Rejected requisition {$requisition->requisition_no}");

        return back()->with('success', "Requisition {$requisition->requisition_no} rejected.");
    }

    /**
     * Complete the removal section (aircraft technician, post-issue). Records the
     * removed unit and drives its serial through the state machine to
     * at_repair / removed_unserviceable.
     */
    public function completeRemoval(Request $request, Requisition $requisition, SerialStateService $serials): RedirectResponse
    {
        abort_unless(in_array($requisition->status, ['issued', 'closed'], true), 422, 'Removal is recorded after the part is issued.');

        $data = $request->validate([
            'serial_no_removed' => ['required', 'string', 'max:255'],
            'removal_zone' => ['nullable', 'string', 'max:100'],
            'unit_changed_by' => ['nullable', 'string', 'max:255'],
            'reason_for_removal' => ['required', 'string', 'max:2000'],
            'removal_signature' => ['nullable', 'string', 'max:255'],
            'removal_date' => ['nullable', 'date'],
            'repair_facility' => ['nullable', 'string', 'max:255'],
            'date_sent' => ['nullable', 'date'],
            'repair_order_ref' => ['nullable', 'string', 'max:255'],
            'disposition' => ['nullable', Rule::in(['at_repair', 'removed_unserviceable'])],
        ]);

        // Sent to a workshop ⇒ at_repair; otherwise condemned/unserviceable.
        $disposition = $data['disposition'] ?? ($data['repair_facility'] ?? null ? 'at_repair' : 'removed_unserviceable');

        // Transition the removed serial if it is tracked in the system.
        $serial = PartSerial::where('serial_number', $data['serial_no_removed'])
            ->when($requisition->aircraft_id, fn ($q) => $q->where('current_aircraft_id', $requisition->aircraft_id))
            ->first()
            ?? PartSerial::where('serial_number', $data['serial_no_removed'])->first();

        if ($serial) {
            $serials->remove($serial, $disposition, $request->user(), $data['reason_for_removal']);
            $data['removed_serial_id'] = $serial->id;
        }

        $requisition->update($data + ['removal_completed_at' => now()]);

        activity('requisition')->causedBy($request->user())->performedOn($requisition)
            ->event('removal')->log("Recorded removal on requisition {$requisition->requisition_no} → {$disposition}");

        return back()->with('success', 'Removal information recorded.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'aircraft_id' => ['nullable', 'exists:aircraft,id'],
            'aircraft_reg' => ['nullable', 'string', 'max:50'],
            'engine_serial_no' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'authorised_by' => ['nullable', 'string', 'max:255'],
            'supply_source' => ['nullable', 'string', 'max:255'],
            'full_description' => ['required', 'string', 'max:500'],
            'part_id' => ['nullable', 'exists:parts,id'],
            'part_no' => ['nullable', 'string', 'max:100'],
            'stock_code' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'batch_no' => ['nullable', 'string', 'max:100'],
            'batch_year' => ['nullable', 'string', 'max:10'],
            'bin_bal_line_no' => ['nullable', 'string', 'max:100'],
            'requisition_date' => ['nullable', 'date'],
        ]);
    }

    protected function formData(Request $request): array
    {
        return [
            'aircraft' => Aircraft::orderBy('registration')->get(['id', 'registration']),
            'parts' => Part::orderBy('part_number')->get(['id', 'part_number', 'description', 'stock_code']),
            'workOrders' => WorkOrder::whereIn('status', ['open', 'in_progress'])
                ->orderByDesc('id')->get(['id', 'wo_ref', 'aircraft_id']),
            'workOrderId' => $request->integer('work_order_id') ?: null,
        ];
    }

    protected function row(Requisition $r): array
    {
        return [
            'id' => $r->id,
            'requisition_no' => $r->requisition_no,
            'status' => $r->status,
            'full_description' => $r->full_description,
            'part_no' => $r->part_no,
            'aircraft_reg' => $r->aircraft?->registration ?? $r->aircraft_reg,
            'wo_ref' => $r->workOrder?->wo_ref,
            'date' => $r->requisition_date?->toDateString(),
        ];
    }

    protected function fullRow(Requisition $r): array
    {
        return $this->row($r) + [
            'work_order_id' => $r->work_order_id,
            'engine_serial_no' => $r->engine_serial_no,
            'position' => $r->position,
            'authorised_by' => $r->authorised_by,
            'supply_source' => $r->supply_source,
            'stock_code' => $r->stock_code,
            'serial_number' => $r->serial_number,
            'batch_no' => $r->batch_no,
            'batch_year' => $r->batch_year,
            'bin_bal_line_no' => $r->bin_bal_line_no,
            'issued_by' => $r->issued_by,
            'unit_serviced_by' => $r->unit_serviced_by,
            'unit_serviced_date' => $r->unit_serviced_date?->toDateString(),
            'requested_by' => $r->requestedBy?->name,
            'approved_by' => $r->approvedBy?->name,
            'approval_remarks' => $r->approval_remarks,
            // Removal section
            'serial_no_removed' => $r->serial_no_removed,
            'removal_zone' => $r->removal_zone,
            'unit_changed_by' => $r->unit_changed_by,
            'reason_for_removal' => $r->reason_for_removal,
            'removal_signature' => $r->removal_signature,
            'removal_date' => $r->removal_date?->toDateString(),
            'repair_facility' => $r->repair_facility,
            'date_sent' => $r->date_sent?->toDateString(),
            'repair_order_ref' => $r->repair_order_ref,
            'removal_completed_at' => $r->removal_completed_at?->toDayDateTimeString(),
        ];
    }
}
