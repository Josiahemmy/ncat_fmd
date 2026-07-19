<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoPurger;
use Illuminate\Console\Command;

class DemoPurge extends Command
{
    protected $signature = 'demo:purge
        {--i-understand-this-deletes-all-transactional-data : Required acknowledgement}
        {--no-interaction-confirmed : Skip the typed app-name confirmation (documented automated use)}';

    protected $description = 'DESTRUCTIVE: empties ALL transactional tables to return the system to a clean pre-launch state.';

    public function handle(DemoPurger $purger): int
    {
        $this->warn('demo:purge empties EVERY transactional table (stock, documents, movements, activity).');
        $this->line('Premise: production has NOT started, so a full truncate is safe. This STOPS being true the day real data enters — do not run it thereafter.');

        if (! $this->option('i-understand-this-deletes-all-transactional-data')) {
            $this->error('Refused: pass --i-understand-this-deletes-all-transactional-data to proceed.');

            return self::FAILURE;
        }

        if (! $this->option('no-interaction-confirmed')) {
            $expected = (string) config('app.name');
            $typed = (string) $this->ask("Type the application name (\"{$expected}\") to confirm the purge");
            if (trim($typed) !== $expected) {
                $this->error('Confirmation did not match — aborted. Nothing was deleted.');

                return self::FAILURE;
            }
        }

        try {
            $this->info('Backing up the database before purge…');
            $report = $purger->purge();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->renderReport($report);

        if (! $report['clean']) {
            $this->error('Purge verification FAILED: some transactional tables are not empty.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✔ Purge complete. System is back to a clean pre-launch state.');
        $this->line('Next: confirm real document-counter start values, then enter real opening balances.');

        return self::SUCCESS;
    }

    protected function renderReport(array $report): void
    {
        $this->newLine();
        $this->line('<comment>Transactional tables (must all be 0):</comment>');
        $this->table(['Table', 'Rows'], collect($report['transactional'])->map(fn ($n, $t) => [$t, $n])->values()->all());

        $this->line('<comment>Preserved reference data:</comment>');
        $this->table(['Table', 'Rows'], collect($report['preserved'])->map(fn ($n, $t) => [$t, $n])->values()->all());

        $this->line('<comment>Document counters (restored, unconfirmed):</comment>');
        $this->table(['Series', 'Next', 'Confirmed'],
            collect($report['counters'])->map(fn ($c, $s) => [$s, $c['next_number'], $c['confirmed'] ? 'yes' : 'no'])->values()->all());
    }
}
