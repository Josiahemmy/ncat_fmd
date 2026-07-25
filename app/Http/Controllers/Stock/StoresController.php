<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Store;
use App\Services\Documents\SivService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoresController extends Controller
{
    public function index(): Response
    {
        $stores = Store::orderBy('sort_order')->get()->map(function (Store $store) {
            $rows = StockBalance::where('store_id', $store->id)->where('quantity', '>', 0)
                ->with('part:id,unit_price')->get();

            return [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'type' => $store->type,
                'description' => $store->description,
                'is_active' => $store->is_active,
                'item_count' => $rows->count(),
                'total_value' => (float) $rows->sum(fn ($b) => (float) $b->quantity * (float) ($b->part->unit_price ?? 0)),
                'awaiting_certification' => $store->type === 'quarantine' ? $rows->count() : null,
            ];
        });

        return Inertia::render('Stock/Stores/Index', ['stores' => $stores]);
    }

    public function show(Request $request, Store $store): Response
    {
        $search = $request->string('search')->toString();

        $rows = StockBalance::where('store_id', $store->id)
            ->where('quantity', '>', 0)
            ->with(['part' => fn ($q) => $q->with('ataChapter:id,chapter_number')])
            ->get()
            ->when($search, fn ($c) => $c->filter(fn ($b) => str_contains(strtolower($b->part->part_number.' '.$b->part->description), strtolower($search))))
            ->map(function (StockBalance $b) use ($store) {
                // Received/aging date for the quarantine certification queue.
                $received = $store->type === 'quarantine'
                    ? StockMovement::where('part_id', $b->part_id)->where('store_id', $store->id)
                        ->where('direction', 'in')->min('posted_at')
                    : null;

                return [
                    'part_id' => $b->part_id,
                    'part_number' => $b->part->part_number,
                    'description' => $b->part->description,
                    'ata' => $b->part->ataChapter?->chapter_number,
                    'quantity' => (float) $b->quantity,
                    'unit_of_issue' => $b->part->unit_of_issue,
                    'is_flammable' => $b->part->is_flammable,
                    'is_serialized' => $b->part->is_serialized,
                    'received_at' => $received ? \Illuminate\Support\Carbon::parse($received)->toDateString() : null,
                    'aging_days' => $received ? (int) \Illuminate\Support\Carbon::parse($received)->diffInDays(now()) : null,
                ];
            })
            ->values();

        return Inertia::render('Stock/Stores/Show', [
            'store' => [
                'id' => $store->id, 'name' => $store->name, 'slug' => $store->slug,
                'type' => $store->type, 'description' => $store->description,
                // Quarantine stays view-only: certification is the only action
                // there, so no requisition or issue may be raised from it.
                'allows_documents' => $store->type !== 'quarantine',
                // Issuing draws on bonded/dope stock only.
                'allows_issue' => in_array($store->type, SivService::ISSUABLE_STORE_TYPES, true),
            ],
            'rows' => $rows,
            'filters' => ['search' => $search],
            // Destinations for a transfer (exclude self + quarantine).
            'transferTargets' => Store::where('id', '!=', $store->id)
                ->where('type', '!=', 'quarantine')->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }
}
