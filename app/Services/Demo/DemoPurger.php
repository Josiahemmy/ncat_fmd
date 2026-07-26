<?php

namespace App\Services\Demo;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * The destructive demo teardown (Builder Prompt #7). Design premise: production
 * has NOT started, so every transactional table can be emptied wholesale — no
 * row-tagging. That premise stops being true the day real data enters, which is
 * why the command wrapping this service is guarded to the teeth.
 *
 * Order of operations: back up FIRST (abort if it fails), then empty
 * transactional tables in strict child→parent order (so FK constraints hold on
 * every engine), delete demo users, restore document counters from the seed-time
 * snapshot, and clear the demo flag. Returns a verification report.
 */
class DemoPurger
{
    /**
     * Transactional tables, CHILD → PARENT. Deleting in this order never
     * violates a foreign key, whether or not FK enforcement can be toggled
     * (sqlite-in-transaction can't; MySQL can).
     */
    public const TRANSACTIONAL = [
        'notifications',
        'activity_log',
        'stock_movements',
        'stock_balances',
        // Loans reference parts and serials, and nothing references loans.
        'loans',
        'siv_items',
        'sivs',
        // SRV items reference purchase order lines, so they go before the order.
        'srv_items',
        // SRVs reference shipments, so they go before the shipment.
        'srvs',
        'shipment_events',
        'shipments',
        'repair_order_lines',
        'repair_orders',
        'purchase_order_lines',
        'purchase_orders',
        'requisition_approvals',
        'requisitions',
        'work_orders',
        'part_serials',
        'part_batches',
        'parts',
    ];

    /** Reference tables that must survive a purge (for the verification report). */
    public const PRESERVED = ['users', 'roles', 'permissions', 'aircraft', 'aircraft_types', 'ata_chapters', 'stores', 'document_counters', 'approval_workflows', 'approval_levels', 'vendors', 'app_settings'];

    public function __construct(protected DemoBackup $backup, protected DemoMode $demo)
    {
    }

    /**
     * @return array{backup_ok: bool, transactional: array<string,int>, preserved: array<string,int>, counters: array<string,array>, demo_users_deleted: int, clean: bool}
     */
    public function purge(): array
    {
        // Step 1 — safety backup. Abort the whole purge if it fails.
        if (! $this->backup->run()) {
            throw new RuntimeException('Database backup failed. Purge aborted, no data was deleted.');
        }

        // Snapshot the counter restore target before we touch anything.
        $snapshot = $this->demo->counterSnapshot() ?? [];

        DB::transaction(function () use ($snapshot) {
            // Step 2 — empty transactional tables (child → parent).
            Schema::withoutForeignKeyConstraints(function () {
                foreach (self::TRANSACTIONAL as $table) {
                    DB::table($table)->delete();
                }
            });

            // Vendors are reference data, so only the demo-flagged ones go, and
            // they go with a force delete: a soft-deleted row would still be in
            // the table and the guarantee is that nothing demo survives.
            Vendor::withTrashed()->where('is_demo', true)->forceDelete();

            // Remove demo users (+ their role pivots) but never real accounts.
            User::query()->where('is_demo', true)->get()->each(function (User $user) {
                DB::table('model_has_roles')->where('model_id', $user->id)
                    ->where('model_type', $user->getMorphClass())->delete();
                $user->forceFill(['is_demo' => true])->deleteQuietly();
            });

            // Restore counters to their pre-demo values, flagged unconfirmed so the
            // department re-confirms the real starting numbers before go-live.
            foreach ($snapshot as $series => $next) {
                DB::table('document_counters')->where('series', $series)
                    ->update(['next_number' => (int) $next, 'confirmed' => false]);
            }

            // Clear the demo flag + state.
            $this->demo->deactivate();
        });

        return $this->report();
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        $transactional = [];
        foreach (self::TRANSACTIONAL as $table) {
            $transactional[$table] = DB::table($table)->count();
        }

        $preserved = [];
        foreach (self::PRESERVED as $table) {
            if (Schema::hasTable($table)) {
                $preserved[$table] = DB::table($table)->count();
            }
        }

        $counters = DB::table('document_counters')
            ->get(['series', 'next_number', 'confirmed'])
            ->mapWithKeys(fn ($c) => [$c->series => ['next_number' => (int) $c->next_number, 'confirmed' => (bool) $c->confirmed]])
            ->all();

        // Vendors survive a purge as reference data, so the guarantee for them
        // is narrower: no demo-flagged row is left, soft-deleted or otherwise.
        $demoVendors = Schema::hasTable('vendors')
            ? (int) Vendor::withTrashed()->where('is_demo', true)->count()
            : 0;

        return [
            'transactional' => $transactional,
            'preserved' => $preserved,
            'counters' => $counters,
            'demo_vendors' => $demoVendors,
            'clean' => collect($transactional)->every(fn ($n) => $n === 0) && $demoVendors === 0,
        ];
    }
}
