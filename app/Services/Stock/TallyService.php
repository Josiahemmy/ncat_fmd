<?php

namespace App\Services\Stock;

use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Store;

/**
 * Builds the AD38 tally card for a part in a store: brought-forward balance,
 * the in-range ledger lines, and carried-forward totals. Balances come from
 * the movements' own balance_after (the ledger is the source of truth).
 */
class TallyService
{
    /**
     * @return array<string, mixed>
     */
    public function card(Part $part, Store $store, ?string $from = null, ?string $to = null): array
    {
        $base = StockMovement::where('part_id', $part->id)->where('store_id', $store->id);

        // Brought forward = balance at the close of the day before the window.
        $broughtForward = 0.0;
        if ($from) {
            $prior = (clone $base)->whereDate('posted_at', '<', $from)->orderByDesc('id')->first();
            $broughtForward = $prior ? (float) $prior->balance_after : 0.0;
        }

        $lineQuery = clone $base;
        if ($from) {
            $lineQuery->whereDate('posted_at', '>=', $from);
        }
        if ($to) {
            $lineQuery->whereDate('posted_at', '<=', $to);
        }
        $movements = $lineQuery->with(['user:id,name', 'aircraft:id,registration'])->orderBy('id')->get();

        $lines = $movements->map(fn (StockMovement $m) => [
            'date' => $m->posted_at?->toDateString(),
            'reference' => $m->reference ?: '—',
            'particulars' => $this->particulars($m),
            'received' => $m->direction === 'in' ? (float) $m->quantity : null,
            'issued' => $m->direction === 'out' ? (float) $m->quantity : null,
            'balance' => (float) $m->balance_after,
            'user' => $m->user?->name,
            'remarks' => $m->remarks,
        ])->values();

        return [
            'brought_forward' => $broughtForward,
            'lines' => $lines,
            'total_received' => (float) $movements->where('direction', 'in')->sum(fn ($m) => (float) $m->quantity),
            'total_issued' => (float) $movements->where('direction', 'out')->sum(fn ($m) => (float) $m->quantity),
            'carried_forward' => $movements->isNotEmpty()
                ? (float) $movements->last()->balance_after
                : $broughtForward,
        ];
    }

    /**
     * Consolidated all-stores ledger for a part (non-AD38). One chronological
     * view across every store with a recomputed running combined balance —
     * per-store balance_after cannot be summed across the timeline.
     *
     * @return array<string, mixed>
     */
    public function consolidated(Part $part, ?string $from = null, ?string $to = null): array
    {
        $base = StockMovement::where('part_id', $part->id);

        // Combined brought-forward = sum of each store's last balance before the window.
        $broughtForward = 0.0;
        if ($from) {
            foreach ((clone $base)->distinct()->pluck('store_id') as $storeId) {
                $prior = (clone $base)->where('store_id', $storeId)
                    ->whereDate('posted_at', '<', $from)->orderByDesc('id')->first();
                $broughtForward += $prior ? (float) $prior->balance_after : 0.0;
            }
        }

        $lineQuery = clone $base;
        if ($from) {
            $lineQuery->whereDate('posted_at', '>=', $from);
        }
        if ($to) {
            $lineQuery->whereDate('posted_at', '<=', $to);
        }
        $movements = $lineQuery
            ->with(['user:id,name', 'aircraft:id,registration', 'store:id,name'])
            ->orderBy('posted_at')->orderBy('id')->get();

        $running = $broughtForward;
        $lines = $movements->map(function (StockMovement $m) use (&$running) {
            $running += $m->direction === 'in' ? (float) $m->quantity : -(float) $m->quantity;

            return [
                'date' => $m->posted_at?->toDateString(),
                'store' => $m->store?->name,
                'reference' => $m->reference ?: '—',
                'particulars' => $this->particulars($m),
                'received' => $m->direction === 'in' ? (float) $m->quantity : null,
                'issued' => $m->direction === 'out' ? (float) $m->quantity : null,
                'balance' => round($running, 2),
                'user' => $m->user?->name,
                'remarks' => $m->remarks,
            ];
        })->values();

        return [
            'consolidated' => true,
            'brought_forward' => round($broughtForward, 2),
            'lines' => $lines,
            'total_received' => (float) $movements->where('direction', 'in')->sum(fn ($m) => (float) $m->quantity),
            'total_issued' => (float) $movements->where('direction', 'out')->sum(fn ($m) => (float) $m->quantity),
            'carried_forward' => round($running, 2),
        ];
    }

    protected function particulars(StockMovement $m): string
    {
        $label = match ($m->movement_type) {
            'opening_balance' => 'Opening balance',
            'receiving' => 'Received',
            'certification_transfer' => 'Certification',
            'transfer' => $m->direction === 'in' ? 'Transfer in' : 'Transfer out',
            'issue' => 'Issued',
            'fuel_receive' => 'Fuel received',
            'fuel_issue' => 'Fuel issued',
            'adjustment' => 'Adjustment',
            'return' => 'Returned',
            default => ucfirst($m->movement_type),
        };

        if ($m->aircraft) {
            $label .= " - {$m->aircraft->registration}";
        }

        return $label;
    }
}
