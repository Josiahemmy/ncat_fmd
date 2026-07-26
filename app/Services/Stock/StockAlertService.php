<?php

namespace App\Services\Stock;

use App\Models\Part;
use App\Models\PartBatch;
use App\Models\StockMovement;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Queryable stock alerts (spec §5.9 / §6). Engine-level only — the dashboard
 * visualization lands in Phase 4; the notifications bell consumes these now.
 */
class StockAlertService
{
    /**
     * Total on-hand across NCAT-owned stores, as a correlated subquery.
     *
     * Borrowed stock is excluded on purpose: holding fifty of someone else's
     * units does not mean the department no longer needs to order any.
     */
    protected function onHandExpr(): string
    {
        return '(select coalesce(sum(stock_balances.quantity), 0) from stock_balances '
            .'inner join stores on stores.id = stock_balances.store_id '
            .'where stock_balances.part_id = parts.id and stores.owned = 1)';
    }

    /** Parts at or below their reorder level. */
    public function belowReorder(): Collection
    {
        return Part::query()
            ->where('reorder_level', '>', 0)
            ->whereRaw($this->onHandExpr().' <= reorder_level')
            ->get();
    }

    /** Parts at or below their minimum level. */
    public function belowMin(): Collection
    {
        return Part::query()
            ->where('min_level', '>', 0)
            ->whereRaw($this->onHandExpr().' <= min_level')
            ->get();
    }

    /** Parts above their maximum level. */
    public function aboveMax(): Collection
    {
        return Part::query()
            ->whereNotNull('max_level')
            ->whereRaw($this->onHandExpr().' > max_level')
            ->get();
    }

    /** Batches expiring within N days (not yet expired). */
    public function expiringWithin(int $days = 90): Collection
    {
        return PartBatch::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays($days))
            ->with('part')
            ->orderBy('expiry_date')
            ->get();
    }

    /** Batches already past expiry. */
    public function expired(): Collection
    {
        return PartBatch::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', today())
            ->with('part')
            ->get();
    }

    /** Parts sitting in Quarantine longer than N days (awaiting certification). */
    public function quarantineAging(int $days = 30): Collection
    {
        $quarantine = Store::where('type', 'quarantine')->first();
        if (! $quarantine) {
            return collect();
        }

        $partIds = DB::table('stock_balances')
            ->where('store_id', $quarantine->id)
            ->where('quantity', '>', 0)
            ->pluck('part_id');

        return $partIds
            ->map(function ($partId) use ($quarantine, $days) {
                $oldest = StockMovement::where('part_id', $partId)
                    ->where('store_id', $quarantine->id)
                    ->where('direction', 'in')
                    ->min('posted_at');

                // "In quarantine for >= N days" — compare dates directly so this
                // is correct under Carbon 3's signed diffInDays (Laravel 12).
                if ($oldest && \Illuminate\Support\Carbon::parse($oldest)->lte(now()->subDays($days))) {
                    return Part::find($partId);
                }

                return null;
            })
            ->filter()
            ->values();
    }

    /** Compact counts for the notification bell / dashboard summary. */
    public function summary(int $expiryWindow = 90, int $quarantineWindow = 30): array
    {
        return [
            'below_reorder' => $this->belowReorder()->count(),
            'below_min' => $this->belowMin()->count(),
            'above_max' => $this->aboveMax()->count(),
            'expiring' => $this->expiringWithin($expiryWindow)->count(),
            'expired' => $this->expired()->count(),
            'quarantine_aging' => $this->quarantineAging($quarantineWindow)->count(),
        ];
    }
}
