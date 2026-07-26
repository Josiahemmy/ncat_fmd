<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\AircraftType;
use App\Models\Part;
use App\Models\PartSerial;
use App\Models\RepairOrder;
use App\Models\Vendor;
use App\Services\Documents\DocumentNumberService;
use App\Services\Documents\OrderSettings;
use App\Services\Documents\RepairOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RepairOrderController extends Controller
{
    public function __construct(protected RepairOrderService $orders)
    {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->string('status')->toString(),
            'search' => $request->string('search')->toString(),
        ];

        return Inertia::render('Orders/RepairOrders/Index', [
            'orders' => RepairOrder::query()
                ->with('vendor:id,name')->withCount('lines')
                ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
                ->when($filters['search'], fn ($q, $v) => $q->where(fn ($w) => $w
                    ->where('ro_number', 'like', "%{$v}%")
                    ->orWhereHas('vendor', fn ($r) => $r->where('name', 'like', "%{$v}%"))))
                ->orderByDesc('id')->get()
                ->map(fn (RepairOrder $o) => [
                    'id' => $o->id,
                    'ro_number' => $o->ro_number,
                    'order_date' => $o->order_date?->toDateString(),
                    'vendor' => $o->vendor?->name,
                    'status' => $o->status,
                    'priority' => $o->priorityLabel(),
                    'line_count' => $o->lines_count,
                ]),
            'filters' => $filters,
            'can' => $this->abilities($request),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Orders/RepairOrders/Create', $this->formProps());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $order = RepairOrder::create([
            'order_date' => $data['order_date'],
            'vendor_id' => $data['vendor_id'],
            'aircraft_type_label' => $data['aircraft_type_label'] ?? null,
            'priority' => $data['priority'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->orders->saveLines($order, $data['lines']);

        return redirect()->route('repair-orders.show', $order)
            ->with('success', 'Repair order saved as a draft. It gets its reference when you issue it.');
    }

    public function show(Request $request, RepairOrder $repairOrder): Response
    {
        $repairOrder->load([
            'vendor', 'lines.part:id,part_number,description',
            'lines.partSerial:id,serial_number,status',
            'lines.requisition:id,requisition_no,repair_order_ref',
            'issuedBy:id,name',
        ]);

        return Inertia::render('Orders/RepairOrders/Show', [
            'order' => $this->orderPayload($repairOrder),
            'can' => $this->abilities($request),
        ]);
    }

    public function edit(RepairOrder $repairOrder): Response
    {
        abort_unless($repairOrder->isDraft(), 422, 'An issued repair order is read-only.');

        $repairOrder->load(['lines.partSerial:id,serial_number,status']);

        return Inertia::render('Orders/RepairOrders/Edit', $this->formProps() + [
            'order' => $this->orderPayload($repairOrder),
        ]);
    }

    public function update(Request $request, RepairOrder $repairOrder): RedirectResponse
    {
        abort_unless($repairOrder->isDraft(), 422, 'An issued repair order is read-only.');

        $data = $this->validated($request);

        $repairOrder->update([
            'order_date' => $data['order_date'],
            'vendor_id' => $data['vendor_id'],
            'aircraft_type_label' => $data['aircraft_type_label'] ?? null,
            'priority' => $data['priority'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        $this->orders->saveLines($repairOrder, $data['lines']);

        return redirect()->route('repair-orders.show', $repairOrder)
            ->with('success', 'Repair order updated.');
    }

    public function issue(Request $request, RepairOrder $repairOrder): RedirectResponse
    {
        $order = $this->orders->issue($repairOrder, $request->user());

        return redirect()->route('repair-orders.show', $order)
            ->with('success', "Repair order issued as {$order->ro_number}. Its serials are now at repair.");
    }

    public function atVendor(Request $request, RepairOrder $repairOrder): RedirectResponse
    {
        $this->orders->markAtVendor($repairOrder, $request->user());

        return back()->with('success', 'Repair order marked as with the vendor.');
    }

    public function returned(Request $request, RepairOrder $repairOrder): RedirectResponse
    {
        $data = $request->validate([
            'dispositions' => ['required', 'array', 'min:1'],
            'dispositions.*.line_id' => ['required', 'integer'],
            'dispositions.*.disposition' => ['required', Rule::in(['serviceable', 'scrapped'])],
            'dispositions.*.note' => ['nullable', 'string', 'max:1000'],
        ]);

        $byLine = [];
        foreach ($data['dispositions'] as $row) {
            $byLine[$row['line_id']] = ['disposition' => $row['disposition'], 'note' => $row['note'] ?? null];
        }

        $this->orders->markReturned($repairOrder, $byLine, $request->user());

        return back()->with(
            'success',
            'Units booked back in. Serviceable units are in Quarantine awaiting certification.',
        );
    }

    public function close(Request $request, RepairOrder $repairOrder): RedirectResponse
    {
        $this->orders->close($repairOrder, $request->user());

        return back()->with('success', "Repair order {$repairOrder->ro_number} closed.");
    }

    public function cancel(Request $request, RepairOrder $repairOrder): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->orders->cancel($repairOrder, $data['cancel_reason'], $request->user());

        return back()->with('success', 'Repair order cancelled.');
    }

    /** @return array<string, mixed> */
    protected function orderPayload(RepairOrder $order): array
    {
        return [
            'id' => $order->id,
            'ro_number' => $order->ro_number,
            'order_date' => $order->order_date?->toDateString(),
            'vendor_id' => $order->vendor_id,
            'vendor' => $order->vendor ? [
                'id' => $order->vendor->id,
                'name' => $order->vendor->name,
                'address_lines' => $order->vendor->addressLines(),
                'country' => $order->vendor->country,
            ] : null,
            'aircraft_type_label' => $order->aircraft_type_label,
            'priority' => $order->priority,
            'priority_label' => $order->priorityLabel(),
            'status' => $order->status,
            'issued_at' => $order->issued_at?->toDayDateTimeString(),
            'issued_by' => $order->issuedBy?->name,
            'returned_at' => $order->returned_at?->toDayDateTimeString(),
            'cancel_reason' => $order->cancel_reason,
            'remarks' => $order->remarks,
            'is_draft' => $order->isDraft(),
            'is_awaiting_return' => $order->isAwaitingReturn(),
            'lines' => $order->lines->map(fn ($l) => [
                'id' => $l->id,
                'line_no' => $l->line_no,
                'description' => $l->description,
                'part_id' => $l->part_id,
                'part_number' => $l->part_number,
                'part_serial_id' => $l->part_serial_id,
                'serial_no' => $l->serial_no,
                'serial_status' => $l->partSerial?->status,
                'requisition' => $l->requisition ? [
                    'id' => $l->requisition->id,
                    'requisition_no' => $l->requisition->requisition_no,
                ] : null,
                'qty' => $l->qty,
                'action' => $l->action,
                'disposition' => $l->disposition,
                'disposition_note' => $l->disposition_note,
            ]),
        ];
    }

    /** @return array<string, mixed> */
    protected function formProps(): array
    {
        return [
            // Only repair-capable vendors, so an unusable vendor cannot be
            // picked in the first place. The service re-checks on issue.
            'vendors' => Vendor::where('is_active', true)->repairCapable()
                ->orderBy('name')->get(['id', 'name', 'type', 'country']),
            'parts' => Part::orderBy('part_number')->get(['id', 'part_number', 'description']),
            'aircraftTypes' => AircraftType::orderBy('name')->pluck('name'),
            'serials' => $this->orders->selectableSerials()->get()
                ->map(fn (PartSerial $s) => [
                    'id' => $s->id,
                    'serial_number' => $s->serial_number,
                    'status' => $s->status,
                    'part_id' => $s->part_id,
                    'part_number' => $s->part?->part_number,
                    'description' => $s->part?->description,
                ]),
            'nextSerial' => app(DocumentNumberService::class)->peek('repair_order'),
            'settings' => app(OrderSettings::class)->all(),
        ];
    }

    /** @return array<string, bool> */
    protected function abilities(Request $request): array
    {
        return [
            'create' => $request->user()->can('orders.create'),
            'edit' => $request->user()->can('orders.edit'),
            'close' => $request->user()->can('orders.close'),
        ];
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'order_date' => ['required', 'date'],
            'vendor_id' => [
                'required',
                Rule::exists('vendors', 'id')->whereIn('type', ['repair_organization', 'both']),
            ],
            'aircraft_type_label' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(RepairOrder::PRIORITIES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:1000'],
            'lines.*.part_id' => ['nullable', 'exists:parts,id'],
            'lines.*.part_number' => ['nullable', 'string', 'max:255'],
            'lines.*.part_serial_id' => ['nullable', 'exists:part_serials,id'],
            'lines.*.serial_no' => ['nullable', 'string', 'max:255'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.action' => ['nullable', 'string', 'max:100'],
        ], [
            'vendor_id.exists' => 'A repair order must be addressed to a repair organisation.',
        ]);

        // See the note in PurchaseOrderController: validated() does not preserve
        // element order, and line order is the printed S/N.
        ksort($data['lines']);

        return $data;
    }
}
