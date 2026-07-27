<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoMode;
use App\Services\Demo\DemoPurger;
use App\Services\Demo\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Replace an existing demo with a fresh one.
 *
 * This exists so that refreshing demonstration data does not require anyone to
 * type the purge command against production. The safety property that makes it
 * reasonable is narrow and deliberate: it refuses unless demo mode is currently
 * ON, which is only true when `demo:seed` created the data and nothing has
 * purged it since. So it can only ever destroy rows the demo itself wrote.
 *
 * If demo mode is off and the tables still hold data, that data came from
 * somewhere else and this command will not touch it. Run `demo:status` to see
 * which situation you are in.
 */
class DemoReseed extends Command
{
    protected $signature = 'demo:reseed {--i-understand-this-replaces-the-current-demo : Required acknowledgement}';

    protected $description = 'Replace the current demo data with a freshly seeded narrative. Refuses unless demo mode is on.';

    public function handle(DemoMode $demo, DemoPurger $purger, DemoSeeder $seeder): int
    {
        if (! $this->option('i-understand-this-replaces-the-current-demo')) {
            $this->error('Refused: pass --i-understand-this-replaces-the-current-demo to proceed.');

            return self::FAILURE;
        }

        if (! $demo->isActive()) {
            $this->error('Refused: demo mode is OFF.');
            $this->newLine();
            $this->line('That means nothing in this system is flagged as demo data, so this command');
            $this->line('cannot tell disposable rows from real stores work and will not guess.');
            $this->newLine();
            $this->line('Run <info>demo:status</info> to see what is actually there.');
            $this->line('If the system is empty, <info>demo:seed</info> is the command you want.');

            return self::FAILURE;
        }

        $this->warn('Demo mode is ON, so the existing transactional data was created by demo:seed.');
        $this->line('Replacing it. A database backup is taken before anything is deleted.');
        $this->newLine();

        try {
            $report = $purger->purge();
        } catch (\Throwable $e) {
            $this->error('Purge failed, so nothing was deleted: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $report['clean']) {
            $this->error('Purge did not leave the system clean. Stopping rather than seeding on top.');

            return self::FAILURE;
        }

        $this->info('✔ Previous demo removed.');
        $this->newLine();

        $seeder->run();

        $this->newLine();
        $this->info('✔ Fresh demo seeded and demo mode is ON.');
        $this->line('Demo users share password: <comment>'.DemoSeeder::DEMO_PASSWORD.'</comment> (domain '.DemoSeeder::DEMO_DOMAIN.')');
        $this->warn('This is DEMO data. Run `php artisan demo:purge` before real operation begins.');

        return self::SUCCESS;
    }
}
