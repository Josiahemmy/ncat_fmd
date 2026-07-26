<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\AircraftType;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\Documents\DocumentNumberService;
use App\Services\Documents\OrderSettings;
use App\Services\Documents\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $orders)
    {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->string('status')->toString(),
            'search' => $request->string('search')->toString(),
        ];

        return Inertia::render('Orders/PurchaseOrders/Index', [
            'orders' => PurchaseOrder::query()
                ->with('vendor:id,name')->withCount('lines')
                ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
                ->when($filters['search'], fn ($q, $v) => $q->where(fn ($w) => $w
                    ->where('po_number', 'like', "%{$v}%")
                    ->orWhereHas('vendor', fn ($r) => $r->where('name', 'like', "%{$v}%"))))
                ->orderByDesc('id')->get()
                ->map(fn (PurchaseOrder $o) => [
                    'id' => $o->id,
                    'po_number' => $o->po_number,
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

    public function create(Request $request): Response
    {
        return Inertia::render('Orders/PurchaseOrders/Create', $this->formProps());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $order = PurchaseOrder::create([
            'order_date' => $data['order_date'],
            'vendor_id' => $data['vendor_id'],
            'aircraft_type_label' => $data['aircraft_type_label'] ?? null,
            'priority' => $data['priority'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->orders->saveLines($order, $data['lines']);

        return redirect()->route('purchase-orders.show', $order)
            ->with('success', 'Purchase order saved as a draft. It gets its reference when you issue it.');
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load([
            'vendor', 'lines.part:id,part_number,description',
            'issuedBy:id,name', 'srvs:id,srv_number,srv_date,purchase_order_id,status',
        ]);

        return Inertia::render('Orders/PurchaseOrders/Show', [
            'order' => $this->orderPayload($purchaseOrder),
            'can' => $this->abilities($request),
        ]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($purchaseOrder->isDraft(), 422, 'An issued purchase order is read-only.');

        $purchaseOrder->load('lines');

        return Inertia::render('Orders/PurchaseOrders/Edit', $this->formProps() + [
            'order' => $this->orderPayload($purchaseOrder),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->isDraft(), 422, 'An issued purchase order is read-only.');

        $data = $this->validated($request);

        $purchaseOrder->update([
            'order_date' => $data['order_date'],
            'vendor_id' => $data['vendor_id'],
            'aircraft_type_label' => $data['aircraft_type_label'] ?? null,
            'priority' => $data['priority'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        $this->orders->saveLines($purchaseOrder, $data['lines']);

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order updated.');
    }

    public function issue(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $order = $this->orders->issue($purchaseOrder, $request->user());

        return redirect()->route('purchase-orders.show', $order)
            ->with('success', "Purchase order issued as {$order->po_number}.");
    }

    public function close(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->orders->close($purchaseOrder, $request->user());

        return back()->with('success', "Purchase order {$purchaseOrder->po_number} closed.");
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->orders->cancel($purchaseOrder, $data['cancel_reason'], $request->user());

        return back()->with('success', 'Purchase order cancelled.');
    }

    /** @return array<string, mixed> */
    protected function orderPayload(PurchaseOrder $order): array
    {
        return [
            'id' => $order->id,
            'po_number' => $order->po_number,
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
            'cancel_reason' => $order->cancel_reason,
            'remarks' => $order->remarks,
            'is_draft' => $order->isDraft(),
            'is_receivable' => $order->isReceivable(),
            'lines' => $order->lines->map(fn ($l) => [
                'id' => $l->id,
                'line_no' => $l->line_no,
                'description' => $l->description,
                'part_id' => $l->part_id,
                'part_number' => $l->part_number,
                'qty_to_order' => $l->qty_to_order,
                'qty_received' => $l->qty_received,
                'outstanding' => $l->outstanding(),
                'line_status' => $l->line_status,
                'timeline_month' => $l->timeline_month,
                'timeline_year' => $l->timeline_year,
                'timeline_label' => $l->timelineLabel(),
            ]),
            'srvs' => $order->relationLoaded('srvs')
                ? $order->srvs->map(fn ($s) => [
                    'id' => $s->id,
                    'srv_number' => $s->srv_number,
                    'srv_date' => $s->srv_date?->toDateString(),
                    'status' => $s->status,
                ])
                : [],
        ];
    }

    /** @return array<string, mixed> */
    protected function formProps(): array
    {
        return [
            'vendors' => Vendor::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'type', 'country']),
            'parts' => Part::orderBy('part_number')->get(['id', 'part_number', 'description']),
            'aircraftTypes' => AircraftType::orderBy('name')->pluck('name'),
            'nextSerial' => app(DocumentNumberService::class)->peek('purchase_order'),
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
            'vendor_id' => ['required', 'exists:vendors,id'],
            'aircraft_type_label' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(PurchaseOrder::PRIORITIES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:1000'],
            'lines.*.part_id' => ['nullable', 'exists:parts,id'],
            'lines.*.part_number' => ['nullable', 'string', 'max:255'],
            'lines.*.qty_to_order' => ['required', 'numeric', 'gt:0'],
            'lines.*.line_status' => ['nullable', 'string', 'max:100'],
            'lines.*.timeline_month' => ['nullable', 'integer', 'between:1,12'],
            'lines.*.timeline_year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        // `validated()` assembles its result rule by rule rather than copying the
        // input array, so a line that omits a nullable key an earlier line
        // supplied comes back out of position. Line order is the printed S/NO.,
        // so it is restored from the client's own indexes.
        ksort($data['lines']);

        return $data;
    }
}
