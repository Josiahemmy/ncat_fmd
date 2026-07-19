<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\AircraftType;
use App\Models\AtaChapter;
use App\Models\Part;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PartController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'ata' => $request->string('ata')->toString(),
            'type' => $request->string('type')->toString(),
            'store' => $request->string('store')->toString(),
            'state' => $request->string('state')->toString(),
        ];

        $stores = Store::orderBy('sort_order')->get(['id', 'name', 'slug', 'type']);

        $parts = Part::query()
            ->with(['ataChapter:id,chapter_number', 'aircraftType:id,name', 'balances', 'batches'])
            ->when($filters['search'], fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('part_number', 'like', "%{$v}%")->orWhere('description', 'like', "%{$v}%")))
            ->when($filters['ata'], fn ($q, $v) => $q->where('ata_chapter_id', $v))
            ->when($filters['type'], fn ($q, $v) => $q->where('aircraft_type_id', $v))
            ->orderBy('part_number')
            ->get()
            ->map(fn (Part $p) => $this->row($p, $stores))
            ->when($filters['state'], fn ($c, $v) => $c->where('state', $v))
            ->when($filters['store'], fn ($c, $v) => $c->filter(fn ($r) => ($r['balances'][$v] ?? 0) > 0))
            ->values();

        return Inertia::render('Stock/Parts/Index', [
            'parts' => $parts,
            'stores' => $stores,
            'ataChapters' => AtaChapter::orderBy('chapter_number')->get(['id', 'chapter_number', 'title']),
            'types' => AircraftType::orderBy('sort_order')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function show(Part $part): Response
    {
        $stores = Store::orderBy('sort_order')->get(['id', 'name', 'slug', 'type']);
        $part->load(['ataChapter', 'aircraftType', 'balances.store', 'batches', 'serials.currentStore']);

        return Inertia::render('Stock/Parts/Show', [
            'part' => $this->row($part, $stores) + [
                'stock_code' => $part->stock_code,
                'ledger_folio' => $part->ledger_folio,
                'bin_location' => $part->bin_location,
                'notes' => $part->notes,
                'ata_chapter_id' => $part->ata_chapter_id,
                'aircraft_type_id' => $part->aircraft_type_id,
            ],
            'batches' => $part->batches->map(fn ($b) => [
                'id' => $b->id, 'batch_number' => $b->batch_number, 'batch_year' => $b->batch_year,
                'expiry_date' => $b->expiry_date?->toDateString(), 'qty_received' => (float) $b->qty_received,
                'expired' => $b->isExpired(),
            ]),
            'serials' => $part->serials->map(fn ($s) => [
                'id' => $s->id, 'serial_number' => $s->serial_number, 'status' => $s->status,
                'store' => $s->currentStore?->name, 'position' => $s->position,
            ]),
            'movements' => $part->movements()->with('store:id,name')->latest('id')->limit(50)->get()
                ->map(fn ($m) => [
                    'id' => $m->id, 'date' => $m->posted_at?->toDayDateTimeString(),
                    'store' => $m->store->name, 'type' => $m->movement_type,
                    'direction' => $m->direction, 'quantity' => (float) $m->quantity,
                    'balance_after' => (float) $m->balance_after, 'reference' => $m->reference,
                ]),
            'stores' => $stores,
            'ataChapters' => AtaChapter::orderBy('chapter_number')->get(['id', 'chapter_number', 'title']),
            'types' => AircraftType::orderBy('sort_order')->get(['id', 'name']),
            'documents' => $this->documentsFor($part),
        ]);
    }

    /** Phase 3 touchpoint: paper documents that reference this part. */
    protected function documentsFor(Part $part): array
    {
        $requisitions = \App\Models\Requisition::where('part_id', $part->id)
            ->with('aircraft:id,registration')->latest('id')->limit(25)->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'requisition_no' => $r->requisition_no, 'status' => $r->status,
                'aircraft' => $r->aircraft?->registration ?? $r->aircraft_reg,
                'date' => $r->requisition_date?->toDateString(),
            ]);

        $issues = \App\Models\SivItem::where('part_id', $part->id)
            ->with('siv:id,siv_number,status')->latest('id')->limit(25)->get()
            ->map(fn ($i) => [
                'id' => $i->id, 'siv_id' => $i->siv_id,
                'siv_number' => $i->siv?->siv_number, 'status' => $i->siv?->status,
                'qty_issued' => (float) $i->qty_issued,
            ]);

        return ['requisitions' => $requisitions, 'issues' => $issues];
    }

    public function store(Request $request): RedirectResponse
    {
        $part = Part::create($this->validated($request));
        activity('part')->causedBy($request->user())->performedOn($part)
            ->event('created')->log("Added part {$part->part_number}");

        return back()->with('success', "Part {$part->part_number} added.");
    }

    public function update(Request $request, Part $part): RedirectResponse
    {
        $part->update($this->validated($request, $part));
        activity('part')->causedBy($request->user())->performedOn($part)
            ->event('updated')->log("Updated part {$part->part_number}");

        return back()->with('success', "Part {$part->part_number} updated.");
    }

    public function destroy(Request $request, Part $part): RedirectResponse
    {
        $number = $part->part_number;
        $part->delete();
        activity('part')->causedBy($request->user())->event('deleted')->log("Deleted part {$number}");

        return back()->with('success', "Part {$number} deleted.");
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Part $part = null): array
    {
        return $request->validate([
            'part_number' => ['required', 'string', 'max:100', Rule::unique('parts', 'part_number')->ignore($part?->id)],
            'description' => ['required', 'string', 'max:255'],
            'ata_chapter_id' => ['nullable', Rule::exists('ata_chapters', 'id')],
            'aircraft_type_id' => ['nullable', Rule::exists('aircraft_types', 'id')],
            'stock_code' => ['nullable', 'string', 'max:100'],
            'ledger_folio' => ['nullable', 'string', 'max:100'],
            'unit_of_issue' => ['required', 'string', 'max:20'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'bin_location' => ['nullable', 'string', 'max:100'],
            'min_level' => ['required', 'numeric', 'min:0'],
            'max_level' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'is_serialized' => ['boolean'],
            'has_shelf_life' => ['boolean'],
            'is_flammable' => ['boolean'],
            'is_fuel' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * Build a catalogue row: per-store balances + computed stock state.
     *
     * @return array<string, mixed>
     */
    protected function row(Part $part, $stores): array
    {
        $balances = [];
        $total = 0.0;
        foreach ($stores as $store) {
            $qty = (float) $part->balances->firstWhere('store_id', $store->id)?->quantity ?: 0.0;
            $balances[$store->slug] = $qty;
            $total += $qty;
        }

        return [
            'id' => $part->id,
            'part_number' => $part->part_number,
            'description' => $part->description,
            'ata' => $part->ataChapter?->chapter_number,
            'type' => $part->aircraftType?->name,
            'unit_of_issue' => $part->unit_of_issue,
            'unit_price' => $part->unit_price !== null ? (float) $part->unit_price : null,
            'min_level' => (float) $part->min_level,
            'max_level' => $part->max_level !== null ? (float) $part->max_level : null,
            'reorder_level' => (float) $part->reorder_level,
            'is_serialized' => $part->is_serialized,
            'has_shelf_life' => $part->has_shelf_life,
            'is_flammable' => $part->is_flammable,
            'is_fuel' => $part->is_fuel,
            'balances' => $balances,
            'total_on_hand' => $total,
            'state' => $this->state($part, $total),
        ];
    }

    protected function state(Part $part, float $total): string
    {
        if ($part->has_shelf_life && $part->batches->contains(fn ($b) => $b->expiry_date && $b->expiry_date->lt(today()))) {
            return 'expired';
        }
        if ($part->has_shelf_life && $part->batches->contains(fn ($b) => $b->expiry_date && $b->expiry_date->isBetween(today(), today()->addDays(90)))) {
            return 'expiring';
        }
        if ($part->min_level > 0 && $total <= (float) $part->min_level) {
            return 'below_min';
        }
        if ($part->reorder_level > 0 && $total <= (float) $part->reorder_level) {
            return 'below_reorder';
        }
        if ($part->max_level !== null && $total > (float) $part->max_level) {
            return 'above_max';
        }

        return 'ok';
    }
}
