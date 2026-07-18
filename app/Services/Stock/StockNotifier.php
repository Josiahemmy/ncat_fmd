<?php

namespace App\Services\Stock;

use App\Models\Part;
use App\Models\StockBalance;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Spatie\Permission\Models\Permission;

/**
 * Lightweight posting-time notification writes (database channel). Called by
 * the controllers after a stock-reducing posting; deduped so a part that stays
 * low doesn't spam a fresh notice on every movement.
 */
class StockNotifier
{
    public function checkReorder(Part $part): void
    {
        if ($part->reorder_level <= 0) {
            return;
        }

        $onHand = (float) StockBalance::where('part_id', $part->id)->sum('quantity');
        if ($onHand > (float) $part->reorder_level) {
            return;
        }

        // Recipients: anyone who can see stores (skip if the permission set
        // isn't installed, e.g. in a bare engine test).
        if (! Permission::where(['name' => 'stores.view', 'guard_name' => 'web'])->exists()) {
            return;
        }

        foreach (User::permission('stores.view')->get() as $user) {
            $alreadyOpen = $user->unreadNotifications
                ->where('type', LowStockNotification::class)
                ->contains(fn ($n) => ($n->data['part_id'] ?? null) === $part->id);

            if (! $alreadyOpen) {
                $user->notify(new LowStockNotification($part, $onHand));
            }
        }
    }
}
