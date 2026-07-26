<?php

namespace App\Services\Demo;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        // Attachments before their event; their files go separately, see
        // deleteAttachmentFiles().
        'shipment_event_attachments',
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

    /**
     * Reference tables the purge does not touch at all. Their counts are
     * reported so the reader can see the catalogue is still standing.
     */
    public const UNTOUCHED = ['roles', 'permissions', 'aircraft', 'aircraft_types', 'ata_chapters', 'stores', 'document_counters', 'approval_workflows', 'approval_levels', 'app_settings'];

    /**
     * Tables holding a mix of demo and real rows, where the purge removes the
     * demo rows and leaves the rest. These are reported with a before and after
     * count, because a bare "after" of 0 or 1 reads as though the purge had
     * emptied a table it was supposed to preserve.
     */
    public const DEMO_SCRUBBED = ['users', 'vendors'];

    /**
     * @deprecated Kept so anything still reading this name resolves. The report
     * now separates UNTOUCHED from DEMO_SCRUBBED, because lumping them together
     * is what made `vendors: 0` look like a contradiction.
     */
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

        // Row counts for the mixed tables, taken before the deletes so the
        // report can show "5 users, 4 demo removed, 1 kept" rather than a bare
        // "1" that reads like the purge ate the account list.
        $before = [];
        foreach (self::DEMO_SCRUBBED as $table) {
            $before[$table] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
        }

        DB::transaction(function () use ($snapshot) {
            // Uploaded files are not rows, so a table delete leaves them on
            // disk. Collect and remove them before the rows that name them go,
            // otherwise the paths are gone and the files are unreachable
            // orphans taking up shared-hosting quota.
            $this->deleteAttachmentFiles();

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

        return $this->report($before);
    }

    /**
     * Remove every stored attachment file, then the directory tree they sat
     * in, so nothing survives the purge on disk. Driven off the rows rather
     * than off a directory listing, so a file belonging to a disk the app no
     * longer uses is still found and removed.
     */
    protected function deleteAttachmentFiles(): void
    {
        if (! Schema::hasTable('shipment_event_attachments')) {
            return;
        }

        DB::table('shipment_event_attachments')
            ->select('disk', 'path')
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    Storage::disk($row->disk ?: 'local')->delete($row->path);
                }
            });

        // Sweep the parent directory too: an upload that was written but whose
        // row was rolled back would otherwise linger with nothing pointing at it.
        Storage::disk('local')->deleteDirectory('shipment-events');
    }

    /**
     * @param  array<string,int>  $before  Pre-purge counts for the mixed tables.
     * @return array<string, mixed>
     */
    public function report(array $before = []): array
    {
        $transactional = [];
        foreach (self::TRANSACTIONAL as $table) {
            $transactional[$table] = DB::table($table)->count();
        }

        $untouched = [];
        foreach (self::UNTOUCHED as $table) {
            if (Schema::hasTable($table)) {
                $untouched[$table] = DB::table($table)->count();
            }
        }

        // Before, removed, remaining for the mixed tables. `$before` is empty
        // when report() is called on its own rather than through purge(), in
        // which case the "removed" column is left null rather than guessed.
        $scrubbed = [];
        foreach (self::DEMO_SCRUBBED as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $remaining = DB::table($table)->count();
            $was = $before[$table] ?? null;
            $scrubbed[$table] = [
                'before' => $was,
                'removed' => $was === null ? null : $was - $remaining,
                'remaining' => $remaining,
            ];
        }

        // Retained for callers written against the old shape.
        $preserved = $untouched + array_map(fn ($r) => $r['remaining'], $scrubbed);

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
            'untouched' => $untouched,
            'scrubbed' => $scrubbed,
            'preserved' => $preserved,
            'counters' => $counters,
            'demo_vendors' => $demoVendors,
            'clean' => collect($transactional)->every(fn ($n) => $n === 0) && $demoVendors === 0,
        ];
    }
}
