<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\WorkOrder;
use App\Services\Documents\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkOrderController extends Controller
{
    /** Preset inspection intervals offered for scheduled inspections (plus free text). */
    public const INSPECTION_PRESETS = ['50 HRS', '100 HRS', '200 HRS', '1000 HRS', 'ANNUAL'];

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'aircraft' => $request->string('aircraft')->toString(),
            'type' => $request->string('type')->toString(),
            'status' => $request->string('status')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $rows = WorkOrder::query()
            ->with('aircraft:id,registration')
            ->when($filters['search'], fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('wo_ref', 'like', "%{$v}%")
                ->orWhere('title', 'like', "%{$v}%")
                ->orWhere('raised_by', 'like', "%{$v}%")))
            ->when($filters['aircraft'], fn ($q, $v) => $q->where('aircraft_id', $v))
            ->when($filters['type'], fn ($q, $v) => $q->where('work_type', $v))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['from'], fn ($q, $v) => $q->whereDate('work_date', '>=', $v))
            ->when($filters['to'], fn ($q, $v) => $q->whereDate('work_date', '<=', $v))
            ->orderByDesc('work_date')->orderByDesc('id')
            ->get()
            ->map(fn (WorkOrder $wo) => $this->row($wo));

        return Inertia::render('WorkOrders/Index', [
            'workOrders' => $rows,
            'aircraft' => Aircraft::with('aircraftType:id,name')->orderBy('registration')
                ->get(['id', 'registration', 'aircraft_type_id']),
            'filters' => $filters,
            'presets' => self::INSPECTION_PRESETS,
        ]);
    }

    public function show(WorkOrder $workOrder): Response
    {
        $workOrder->load([
            'aircraft.aircraftType', 'raisedByUser:id,name', 'createdBy:id,name',
            'requisitions' => fn ($q) => $q->orderByDesc('id'),
        ]);

        return Inertia::render('WorkOrders/Show', [
            'workOrder' => $this->row($workOrder) + [
                'description' => $workOrder->description,
                'inspection_type' => $workOrder->inspection_type,
                'qc_checked_by' => $workOrder->qc_checked_by,
                'records_updated_by' => $workOrder->records_updated_by,
                'filed_by' => $workOrder->filed_by,
                'remarks' => $workOrder->remarks,
                'closed_at' => $workOrder->closed_at?->toDayDateTimeString(),
                'created_by' => $workOrder->createdBy?->name,
                'aircraft_type' => $workOrder->aircraft->aircraftType?->name,
            ],
            'requisitions' => $workOrder->requisitions->map(fn ($r) => [
                'id' => $r->id,
                'requisition_no' => $r->requisition_no,
                'status' => $r->status,
                'description' => $r->full_description,
                'part_no' => $r->part_no,
            ]),
            'presets' => self::INSPECTION_PRESETS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $aircraft = Aircraft::with('aircraftType')->findOrFail($data['aircraft_id']);
        $ref = app(DocumentNumberService::class)
            ->reserveWorkOrder($aircraft->aircraftType, Carbon::parse($data['work_date']));

        $workOrder = WorkOrder::create($data + [
            'wo_ref' => $ref,
            'created_by_user_id' => $request->user()->id,
        ]);

        activity('work_order')->causedBy($request->user())->performedOn($workOrder)
            ->event('created')->log("Raised work order {$ref}");

        return redirect()->route('work-orders.show', $workOrder)
            ->with('success', "Work order {$ref} raised.");
    }

    public function edit(WorkOrder $workOrder): Response
    {
        return Inertia::render('WorkOrders/Edit', [
            'workOrder' => $this->row($workOrder) + [
                'description' => $workOrder->description,
                'inspection_type' => $workOrder->inspection_type,
                'qc_checked_by' => $workOrder->qc_checked_by,
                'records_updated_by' => $workOrder->records_updated_by,
                'filed_by' => $workOrder->filed_by,
                'remarks' => $workOrder->remarks,
                'raised_by' => $workOrder->raised_by,
            ],
            'aircraft' => Aircraft::orderBy('registration')->get(['id', 'registration']),
            'presets' => self::INSPECTION_PRESETS,
            'openRequisitions' => $workOrder->openRequisitionsCount(),
        ]);
    }

    public function update(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $this->validated($request, updating: true);

        $wasClosed = $workOrder->isClosed();
        $nowClosing = $data['status'] === 'closed';

        // Closing stamps closed_at; re-opening clears it.
        if ($nowClosing && ! $wasClosed) {
            $data['closed_at'] = now();
        } elseif (! $nowClosing) {
            $data['closed_at'] = null;
        }

        $workOrder->update($data);

        activity('work_order')->causedBy($request->user())->performedOn($workOrder)
            ->event('updated')->log("Updated work order {$workOrder->wo_ref}");

        // Warn (never block) when closing over still-open requisitions.
        $message = "Work order {$workOrder->wo_ref} updated.";
        if ($nowClosing && ! $wasClosed && ($open = $workOrder->openRequisitionsCount()) > 0) {
            return redirect()->route('work-orders.show', $workOrder)
                ->with('warning', "Work order closed with {$open} requisition(s) still open.");
        }

        return redirect()->route('work-orders.show', $workOrder)->with('success', $message);
    }

    /** Shared validation; on create aircraft/work_type are required, on update they may be immutable. */
    protected function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'aircraft_id' => ['required', 'exists:aircraft,id'],
            'work_type' => ['required', Rule::in(['snag', 'scheduled_inspection', 'other'])],
            'inspection_type' => [
                'nullable', 'string', 'max:100',
                Rule::requiredIf(fn () => $request->input('work_type') === 'scheduled_inspection'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => [$updating ? 'required' : 'nullable', Rule::in(['open', 'in_progress', 'closed'])],
            'raised_by' => ['required', 'string', 'max:255'],
            'qc_checked_by' => ['nullable', 'string', 'max:255'],
            'records_updated_by' => ['nullable', 'string', 'max:255'],
            'filed_by' => ['nullable', 'string', 'max:255'],
            'work_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** Register row — matches the Work Order Ledger Log columns. */
    protected function row(WorkOrder $wo): array
    {
        return [
            'id' => $wo->id,
            'wo_ref' => $wo->wo_ref,
            'work_date' => $wo->work_date?->toDateString(),
            'aircraft_id' => $wo->aircraft_id,
            'aircraft_reg' => $wo->aircraft?->registration,
            'work_type' => $wo->work_type,
            'title' => $wo->title,
            'status' => $wo->status,
            'raised_by' => $wo->raised_by,
            'qc_checked_by' => $wo->qc_checked_by,
            'records_updated_by' => $wo->records_updated_by,
            'filed_by' => $wo->filed_by,
        ];
    }
}
