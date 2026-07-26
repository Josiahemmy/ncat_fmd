<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\RepairOrder;
use App\Models\Shipment;
use App\Models\Vendor;
use App\Services\Shipping\ShipmentService;
use App\Services\Shipping\ShipmentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shipping (spec §12.6). Note what is missing: there is no update or destroy
 * action for an event. The only way to change what the timeline says is to add
 * another event to it.
 */
class ShipmentController extends Controller
{
    public function __construct(
        protected ShipmentService $shipments,
        protected ShipmentSettings $settings,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'vendor' => $request->string('vendor')->toString(),
            'status' => $request->string('status')->toString(),
            'state' => $request->string('state')->toString(),   // overdue | in_transit | arrived | closed
            'source' => $request->string('source')->toString(),  // purchase_order | repair_order | standalone
            'search' => $request->string('search')->toString(),
        ];

        $shipments = Shipment::query()
            ->with(['vendor:id,name', 'source'])
            ->when($filters['vendor'], fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($filters['status'], fn ($q, $v) => $q->where('current_status', $v))
            ->when($filters['source'] === 'purchase_order', fn ($q) => $q->where('source_type', PurchaseOrder::class))
            ->when($filters['source'] === 'repair_order', fn ($q) => $q->where('source_type', RepairOrder::class))
            ->when($filters['source'] === 'standalone', fn ($q) => $q->whereNull('source_type'))
            ->when($filters['state'] === 'overdue', fn ($q) => $q
                ->whereNull('arrived_at')->whereNull('closed_at')
                ->whereNotNull('expected_arrival_date')
                ->whereDate('expected_arrival_date', '<', today()))
            ->when($filters['state'] === 'in_transit', fn ($q) => $q->whereNull('arrived_at')->whereNull('closed_at'))
            ->when($filters['state'] === 'arrived', fn ($q) => $q->whereNotNull('arrived_at'))
            ->when($filters['state'] === 'closed', fn ($q) => $q->whereNotNull('closed_at'))
            ->when($filters['search'], fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$v}%")
                ->orWhere('awb_reference', 'like', "%{$v}%")
                ->orWhere('carrier', 'like', "%{$v}%")
                ->orWhere('description', 'like', "%{$v}%")))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Shipment $s) => $this->rowPayload($s));

        return Inertia::render('Logistics/Shipments/Index', [
            'shipments' => $shipments,
            'filters' => $filters,
            'vendors' => Vendor::orderBy('name')->get(['id', 'name']),
            'statuses' => $this->settings->statuses(),
            'can' => $this->abilities($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Logistics/Shipments/Create', $this->formProps());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'source_kind' => ['nullable', Rule::in(['purchase_order', 'repair_order'])],
            'source_id' => ['nullable', 'integer', 'required_with:source_kind'],
            'description' => ['nullable', 'string', 'max:2000'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'awb_reference' => ['nullable', 'string', 'max:255'],
            'expected_arrival_date' => ['nullable', 'date'],
            // The opening event, recorded with the shipment so the timeline is
            // never empty on the first view.
            'status' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date', 'required_with:status'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_arrival' => ['nullable', 'boolean'],
        ]);

        $this->assertSourceExists($data);

        $shipment = $this->shipments->create($data, $request->user());

        return redirect()->route('shipments.show', $shipment)
            ->with('success', "Shipment {$shipment->reference} created.");
    }

    public function show(Request $request, Shipment $shipment): Response
    {
        $shipment->load([
            'vendor', 'source', 'createdBy:id,name',
            'events.recordedBy:id,name',
            'srvs:id,srv_number,srv_date,status,shipment_id',
        ]);

        return Inertia::render('Logistics/Shipments/Show', [
            'shipment' => $this->detailPayload($shipment),
            'statuses' => $this->settings->statuses(),
            'arrivalStatus' => $this->settings->arrivalStatus(),
            'can' => $this->abilities($request),
        ]);
    }

    public function update(Request $request, Shipment $shipment): RedirectResponse
    {
        // Header admin only. The timeline is not reachable from here by design.
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'awb_reference' => ['nullable', 'string', 'max:255'],
            'expected_arrival_date' => ['nullable', 'date'],
        ]);

        $shipment->update($data);

        return back()->with('success', 'Shipment details updated.');
    }

    public function addEvent(Request $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_arrival' => ['nullable', 'boolean'],
        ]);

        $this->shipments->addEvent($shipment, $data, $request->user());

        return back()->with('success', 'Event recorded.');
    }

    public function close(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->shipments->close($shipment);

        return back()->with('success', "Shipment {$shipment->reference} closed.");
    }

    /**
     * Hand off to receiving. The SRV form is opened pre-filled from the
     * shipment and its source order; nothing is posted here, so the existing
     * Quarantine flow is entered exactly as it is entered by hand.
     */
    public function createSrv(Request $request, Shipment $shipment): RedirectResponse
    {
        abort_unless($shipment->hasArrived(), 422, 'This shipment has not been recorded as arrived at NCAT yet.');

        return redirect()->route('receiving.create', ['shipment' => $shipment->id]);
    }

    /** @return array<string, mixed> */
    protected function rowPayload(Shipment $s): array
    {
        return [
            'id' => $s->id,
            'reference' => $s->reference,
            'vendor' => $s->vendor?->name,
            'description' => $s->description,
            'carrier' => $s->carrier,
            'awb_reference' => $s->awb_reference,
            'expected_arrival_date' => $s->expected_arrival_date?->toDateString(),
            'current_status' => $s->current_status,
            'current_status_date' => $s->current_status_date?->toDateString(),
            'source_label' => $s->sourceLabel(),
            'source_kind' => match ($s->source_type) {
                PurchaseOrder::class => 'purchase_order',
                RepairOrder::class => 'repair_order',
                default => null,
            },
            'source_id' => $s->source_id,
            'has_arrived' => $s->hasArrived(),
            'is_closed' => $s->isClosed(),
            'is_overdue' => $s->isOverdue(),
            'days_overdue' => $s->daysOverdue(),
            'days_since_last_event' => $s->daysSinceLastEvent(),
        ];
    }

    /** @return array<string, mixed> */
    protected function detailPayload(Shipment $s): array
    {
        return $this->rowPayload($s) + [
            'created_by' => $s->createdBy?->name,
            'created_at' => $s->created_at?->toDayDateTimeString(),
            'events' => $s->events->map(fn ($e) => [
                'id' => $e->id,
                'status' => $e->status,
                'event_date' => $e->event_date?->toDateString(),
                'note' => $e->note,
                'is_arrival' => $e->is_arrival,
                'recorded_by' => $e->recordedBy?->name ?? 'System',
                'recorded_at' => $e->created_at?->toDayDateTimeString(),
            ])->values(),
            'srvs' => $s->srvs->map(fn ($v) => [
                'id' => $v->id,
                'srv_number' => $v->srv_number,
                'srv_date' => $v->srv_date?->toDateString(),
                'status' => $v->status,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    protected function formProps(): array
    {
        return [
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'purchaseOrders' => PurchaseOrder::whereNotNull('po_number')
                ->orderByDesc('id')->get(['id', 'po_number', 'vendor_id'])
                ->map(fn ($o) => ['id' => $o->id, 'label' => $o->po_number, 'vendor_id' => $o->vendor_id]),
            'repairOrders' => RepairOrder::whereNotNull('ro_number')
                ->orderByDesc('id')->get(['id', 'ro_number', 'vendor_id'])
                ->map(fn ($o) => ['id' => $o->id, 'label' => $o->ro_number, 'vendor_id' => $o->vendor_id]),
            'statuses' => $this->settings->statuses(),
            'arrivalStatus' => $this->settings->arrivalStatus(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function assertSourceExists(array $data): void
    {
        $id = $data['source_id'] ?? null;

        if ($id === null) {
            return;
        }

        match ($data['source_kind'] ?? null) {
            'purchase_order' => PurchaseOrder::findOrFail($id),
            'repair_order' => RepairOrder::findOrFail($id),
            default => null,
        };
    }

    /** @return array<string, bool> */
    protected function abilities(Request $request): array
    {
        return [
            'manage' => $request->user()->can('shipping.manage'),
            'receive' => $request->user()->can('receiving.post'),
        ];
    }
}
