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
        $this->line('Premise: production has NOT started, so a full truncate is safe. This STOPS being true the day real data enters, so do not run it thereafter.');

        if (! $this->option('i-understand-this-deletes-all-transactional-data')) {
            $this->error('Refused: pass --i-understand-this-deletes-all-transactional-data to proceed.');

            return self::FAILURE;
        }

        if (! $this->option('no-interaction-confirmed')) {
            $expected = (string) config('app.name');
            $typed = (string) $this->ask("Type the application name (\"{$expected}\") to confirm the purge");
            if (trim($typed) !== $expected) {
                $this->error('Confirmation did not match. Aborted, nothing was deleted.');

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

    /**
     * Three categories, named for what happened to each rather than for what
     * they are. The reader gets one pass at this, under pressure, to decide
     * whether the system is clean: "Preserved reference data" listing
     * `vendors: 0` read as a contradiction, because it lumped tables nothing
     * touched together with tables that had demo rows taken out of them.
     */
    protected function renderReport(array $report): void
    {
        $this->newLine();
        $this->line('<comment>1. Emptied completely (every count must be 0):</comment>');
        $this->line('   Transactional data. Nothing here survives a purge.');
        $this->table(['Table', 'Rows left'], collect($report['transactional'])->map(fn ($n, $t) => [$t, $n])->values()->all());

        $this->line('<comment>2. Not touched (reference data, left exactly as it was):</comment>');
        $this->line('   The catalogue the system needs to run. The purge does not read or write these.');
        $this->table(['Table', 'Rows'], collect($report['untouched'])->map(fn ($n, $t) => [$t, $n])->values()->all());

        $this->line('<comment>3. Demo rows removed, real rows kept:</comment>');
        $this->line('   Mixed tables. Only rows created by the demo were deleted; anything real stayed.');
        $this->table(
            ['Table', 'Before', 'Demo rows removed', 'Real rows kept'],
            collect($report['scrubbed'])->map(fn ($r, $t) => [
                $t,
                $r['before'] ?? '—',
                $r['removed'] ?? '—',
                $r['remaining'],
            ])->values()->all(),
        );

        $this->line('<comment>Document counters (restored, awaiting confirmation):</comment>');
        $this->table(['Series', 'Next', 'Confirmed'],
            collect($report['counters'])->map(fn ($c, $s) => [$s, $c['next_number'], $c['confirmed'] ? 'yes' : 'no'])->values()->all());
    }
}
