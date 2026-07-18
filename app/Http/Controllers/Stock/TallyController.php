<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Store;
use App\Services\Stock\TallyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TallyController extends Controller
{
    public function __construct(protected TallyService $tally)
    {
    }

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $parts = Part::query()
            ->when($search, fn ($q, $v) => $q->where('part_number', 'like', "%{$v}%")->orWhere('description', 'like', "%{$v}%"))
            ->orderBy('part_number')
            ->limit(50)
            ->get(['id', 'part_number', 'description']);

        return Inertia::render('Stock/Tally/Index', [
            'parts' => $parts,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(Request $request, Part $part): Response
    {
        // Stores this part has ever moved in.
        $storeIds = StockMovement::where('part_id', $part->id)->distinct()->pluck('store_id');
        $stores = Store::whereIn('id', $storeIds)->orderBy('sort_order')->get(['id', 'name', 'slug', 'type']);

        $storeId = $request->integer('store') ?: $stores->first()?->id;
        $store = $storeId ? Store::find($storeId) : null;

        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        $card = $store ? $this->tally->card($part, $store, $from, $to) : null;

        $part->load(['ataChapter:id,chapter_number']);

        return Inertia::render('Stock/Tally/Show', [
            'part' => [
                'id' => $part->id,
                'part_number' => $part->part_number,
                'description' => $part->description,
                'ata' => $part->ataChapter?->chapter_number,
                'ledger_folio' => $part->ledger_folio,
                'bin_location' => $part->bin_location,
                'unit_of_issue' => $part->unit_of_issue,
                'unit_price' => $part->unit_price !== null ? (float) $part->unit_price : null,
                'min_level' => (float) $part->min_level,
                'max_level' => $part->max_level !== null ? (float) $part->max_level : null,
                'reorder_level' => (float) $part->reorder_level,
            ],
            'stores' => $stores,
            'currentStore' => $store ? ['id' => $store->id, 'name' => $store->name] : null,
            'card' => $card,
            'filters' => ['store' => $storeId, 'from' => $from, 'to' => $to],
        ]);
    }
}
