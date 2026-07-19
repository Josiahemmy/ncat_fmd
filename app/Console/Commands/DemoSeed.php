<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoSeeder;
use Illuminate\Console\Command;

class DemoSeed extends Command
{
    protected $signature = 'demo:seed {--force : Seed even if transactional data already exists}';

    protected $description = 'Seed the management-demo narrative (backdated). Refuses to run if data exists unless --force.';

    public function handle(DemoSeeder $seeder): int
    {
        if ($seeder->alreadyPopulated() && ! $this->option('force')) {
            $this->error('Transactional data already exists (or a demo is active).');
            $this->line('Run <info>demo:purge</info> first, or pass <info>--force</info> to seed on top.');

            return self::FAILURE;
        }

        $this->info('Seeding demo narrative (backdated over ~10 weeks)…');
        $seeder->run();

        $this->newLine();
        $this->info('✔ Demo data seeded and demo mode is ON.');
        $this->line('Demo users share password: <comment>'.DemoSeeder::DEMO_PASSWORD.'</comment> (domain '.DemoSeeder::DEMO_DOMAIN.')');
        $this->warn('This is DEMO data. Run `php artisan demo:purge` before real operation begins.');

        return self::SUCCESS;
    }
}
