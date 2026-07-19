<?php

namespace App\Http\Controllers;

use App\Models\Aircraft;
use App\Models\AircraftType;
use App\Models\PartSerial;
use App\Models\Requisition;
use App\Models\Siv;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The aircraft experience (spec §7 module 2): the fleet grid, and the
 * per-registration Aircraft Workspace with its animated action strip,
 * document tabs and CAMP-style Parts-on-Aircraft view.
 */
class AircraftController extends Controller
{
    /** WO statuses that count as "open" on this screen. */
    protected const OPEN_WO = ['open', 'in_progress'];

    /** Fleet grid — the 6 types as premium cards, each with its registrations. */
    public function index(): Response
    {
        // One grouped query for open-WO counts per aircraft (no N+1).
        $openByAircraft = WorkOrder::query()
            ->whereIn('status', self::OPEN_WO)
            ->selectRaw('aircraft_id, count(*) as c')
            ->groupBy('aircraft_id')
            ->pluck('c', 'aircraft_id');

        $types = AircraftType::query()
            ->orderBy('sort_order')
            ->with(['aircraft' => fn ($q) => $q->orderBy('registration')])
            ->get();

        $fleet = $types->map(function (AircraftType $type) use ($openByAircraft) {
            $registrations = $type->aircraft->map(fn (Aircraft $a) => [
                'id' => $a->id,
                'registration' => $a->registration,
                'status' => $a->status,
                'open_wo' => (int) ($openByAircraft[$a->id] ?? 0),
                'href' => route('aircraft.show', $a),
            ]);

            return [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'image' => $type->image_path,
                'fleet_count' => $registrations->count(),
                'open_wo' => (int) $registrations->sum('open_wo'),
                'registrations' => $registrations->values(),
            ];
        });

        return Inertia::render('Aircraft/Fleet', [
            'types' => $fleet,
        ]);
    }

    /** Aircraft Workspace — hero stats, action strip, document tabs, parts-on-aircraft. */
    public function show(Aircraft $aircraft): Response
    {
        $aircraft->load('aircraftType:id,name,slug,image_path');

        $openWorkOrders = WorkOrder::where('aircraft_id', $aircraft->id)->whereIn('status', self::OPEN_WO)->count();
        $pendingRequisitions = Requisition::where('aircraft_id', $aircraft->id)->where('status', 'submitted')->count();

        $partsOnAircraft = $this->partsOnAircraft($aircraft);

        return Inertia::render('Aircraft/Workspace', [
            'aircraft' => [
                'id' => $aircraft->id,
                'registration' => $aircraft->registration,
                'status' => $aircraft->status,
                'type' => $aircraft->aircraftType?->name,
                'type_slug' => $aircraft->aircraftType?->slug,
                'image' => $aircraft->aircraftType?->image_path,
                'notes' => $aircraft->notes,
            ],
            'stats' => [
                'open_work_orders' => $openWorkOrders,
                'pending_requisitions' => $pendingRequisitions,
                'parts_installed' => $partsOnAircraft->count(),
            ],
            // Action-strip navigation, each pre-filtered to this aircraft.
            'links' => [
                'work_orders' => route('work-orders.index', ['aircraft' => $aircraft->id]),
                'requisitions' => route('requisitions.index', ['aircraft' => $aircraft->id]),
                'receiving' => route('receiving.index'),
                'issuing' => route('issuing.index', ['aircraft' => $aircraft->id]),
                'tally' => route('tally-cards.index', ['aircraft_type' => $aircraft->aircraft_type_id]),
            ],
            'workOrders' => $this->workOrders($aircraft),
            'requisitions' => $this->requisitions($aircraft),
            'sivs' => $this->sivs($aircraft),
            'partsOnAircraft' => $partsOnAircraft->values(),
        ]);
    }

    /**
     * Serials currently installed on this airframe, with the install date
     * derived from the movement ledger (falling back to the serial's own
     * timestamp when installed outside a posting).
     */
    protected function partsOnAircraft(Aircraft $aircraft)
    {
        $serials = PartSerial::query()
            ->where('current_aircraft_id', $aircraft->id)
            ->where('status', 'installed')
            ->with('part:id,part_number,description')
            ->get();

        $installedAt = StockMovement::query()
            ->where('aircraft_id', $aircraft->id)
            ->whereIn('part_serial_id', $serials->pluck('id'))
            ->where('direction', 'out')
            ->selectRaw('part_serial_id, MAX(posted_at) as installed_at')
            ->groupBy('part_serial_id')
            ->pluck('installed_at', 'part_serial_id');

        return $serials->map(fn (PartSerial $s) => [
            'serial_id' => $s->id,
            'part_id' => $s->part_id,
            'part_number' => $s->part?->part_number,
            'description' => $s->part?->description,
            'serial_number' => $s->serial_number,
            'position' => $s->position,
            'installed_at' => optional(
                isset($installedAt[$s->id]) ? \Illuminate\Support\Carbon::parse($installedAt[$s->id]) : $s->updated_at
            )->toDateString(),
            'href' => route('parts.show', $s->part_id),
        ]);
    }

    protected function workOrders(Aircraft $aircraft)
    {
        return WorkOrder::query()
            ->where('aircraft_id', $aircraft->id)
            ->orderByDesc('work_date')->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (WorkOrder $wo) => [
                'id' => $wo->id,
                'wo_ref' => $wo->wo_ref,
                'title' => $wo->title,
                'work_type' => $wo->work_type,
                'status' => $wo->status,
                'date' => $wo->work_date?->toDateString(),
                'href' => route('work-orders.show', $wo),
            ]);
    }

    protected function requisitions(Aircraft $aircraft)
    {
        return Requisition::query()
            ->where('aircraft_id', $aircraft->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (Requisition $r) => [
                'id' => $r->id,
                'requisition_no' => $r->requisition_no,
                'description' => $r->full_description,
                'part_no' => $r->part_no,
                'status' => $r->status,
                'href' => route('requisitions.show', $r),
            ]);
    }

    protected function sivs(Aircraft $aircraft)
    {
        return Siv::query()
            ->whereHas('items.requisition', fn ($q) => $q->where('aircraft_id', $aircraft->id))
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (Siv $s) => [
                'id' => $s->id,
                'siv_number' => $s->siv_number,
                'requisition_for' => $s->requisition_for,
                'status' => $s->status,
                'href' => route('issuing.show', $s),
            ]);
    }
}
