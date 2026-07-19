<?php

namespace App\Services\Demo;

use Illuminate\Support\Facades\Artisan;

/**
 * Runs the safety DB backup before a destructive purge. Isolated behind this
 * seam so a test can bind a failing implementation and prove the purge aborts
 * when the backup fails.
 */
class DemoBackup
{
    /** @return bool true if the backup succeeded */
    public function run(): bool
    {
        try {
            $exit = Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);
        } catch (\Throwable $e) {
            return false;
        }

        return $exit === 0;
    }
}
