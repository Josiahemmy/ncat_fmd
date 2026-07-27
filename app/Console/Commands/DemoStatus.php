<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoMode;
use App\Services\Demo\DemoPurger;
use App\Services\Demo\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only. Answers one question before anyone reaches for a destructive
 * command: is there anything in this system, and did the demo put it there?
 *
 * `demo:seed` refuses when transactional data exists, and its message cannot
 * tell you which kind of data you are looking at. That matters enormously: old
 * demo rows are disposable, real stores work is not, and the difference decides
 * whether a purge is routine or a catastrophe.
 */
class DemoStatus extends Command
{
    protected $signature = 'demo:status';

    protected $description = 'Report whether demo mode is on and what is currently in the transactional tables.';

    public function handle(DemoMode $demo, \App\Models\DemoState $stateModel): int
    {
        $active = $demo->isActive();
        $state = $stateModel->newQuery()->latest('id')->first();

        $this->newLine();
        $this->line('<comment>Demo mode</comment>');

        if ($active) {
            $this->line('  <info>ON</info>. The data below was created by demo:seed and is disposable.');
            $this->line('  Seeded: '.($state?->seeded_at ?? 'unknown'));
        } else {
            $this->line('  <info>OFF</info>. Nothing here is flagged as demo data.');
            if ($state?->seeded_at) {
                $this->line('  A demo was seeded previously and purged. Last seeded: '.$state->seeded_at);
            }
        }

        $rows = [];
        $total = 0;
        foreach (DemoSeeder::TRANSACTIONAL_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $count = DB::table($table)->count();
            $total += $count;
            if ($count > 0) {
                $rows[] = [$table, $count];
            }
        }

        $this->newLine();
        $this->line('<comment>Transactional tables holding rows</comment>');

        if ($rows === []) {
            $this->line('  All empty. The system is at a clean pre-launch state.');
        } else {
            $this->table(['Table', 'Rows'], $rows);
        }

        // Vendors are reference data, so they survive a truncate and are only
        // removed by their demo flag. Worth showing for the same reason.
        if (Schema::hasTable('vendors')) {
            $demoVendors = DB::table('vendors')->where('is_demo', true)->count();
            $realVendors = DB::table('vendors')->where('is_demo', false)->count();
            $this->line("  vendors: {$demoVendors} demo, {$realVendors} real");
        }

        $this->newLine();
        $this->line('<comment>What this means</comment>');

        if ($total === 0 && ! $active) {
            $this->info('  Nothing to lose. `demo:seed` will populate the system.');
        } elseif ($active) {
            $this->info('  This is demo data. `demo:reseed` will replace it with a fresh narrative.');
            $this->line('  A database backup is taken before anything is deleted.');
        } else {
            $this->warn('  There is transactional data here that the demo did NOT create.');
            $this->warn('  Treat it as real work. Do not purge or force-seed without checking');
            $this->warn('  with whoever entered it.');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
