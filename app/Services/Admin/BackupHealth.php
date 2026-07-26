<?php

namespace App\Services\Admin;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Reads the backup destination and reports what is actually on disk.
 *
 * The problem this exists for: `delete_oldest_backups_when_using_more_megabytes_than`
 * silently discards the oldest copies once the set exceeds the cap. Nothing is
 * logged where an administrator would see it, and mail transport is deferred,
 * so the first sign of trouble would be a restore that cannot reach far enough
 * back. This turns that into something visible on the Administration dashboard.
 *
 * The tell for silent truncation is a mismatch between two numbers that are
 * both already in config: retention says "keep every backup for N days", so if
 * the OLDEST copy on disk is newer than N days, the cap has been evicting
 * history that retention promised to keep.
 */
class BackupHealth
{
    public function __construct(protected ?string $disk = null, protected ?string $name = null)
    {
        $this->disk ??= config('backup.backup.destination.disks.0', 'local');
        $this->name ??= config('backup.backup.name', config('app.name'));
    }

    /**
     * @return array{
     *     configured: bool,
     *     last: null|array{at: string, age_hours: int, size_bytes: int, size_human: string},
     *     copies: int,
     *     total_bytes: int,
     *     total_human: string,
     *     cap_megabytes: int|null,
     *     used_percent: int|null,
     *     retention_days: int,
     *     oldest_at: string|null,
     *     oldest_age_days: int|null,
     *     status: string,
     *     messages: string[]
     * }
     */
    public function report(): array
    {
        $capMb = config('backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than');
        $retentionDays = (int) config('backup.cleanup.default_strategy.keep_all_backups_for_days', 0);

        $files = $this->backupFiles();

        if ($files === []) {
            return [
                'configured' => true,
                'last' => null,
                'copies' => 0,
                'total_bytes' => 0,
                'total_human' => $this->human(0),
                'cap_megabytes' => $capMb,
                'used_percent' => $capMb ? 0 : null,
                'retention_days' => $retentionDays,
                'oldest_at' => null,
                'oldest_age_days' => null,
                'status' => 'never_run',
                'messages' => ['No backup has been taken yet. Run `php artisan backup:run` and confirm the schedule is active.'],
            ];
        }

        $total = array_sum(array_column($files, 'size'));
        $newest = $files[array_key_last($files)];
        $oldest = $files[0];

        $newestAt = Carbon::createFromTimestamp($newest['time']);
        $oldestAt = Carbon::createFromTimestamp($oldest['time']);
        $ageHours = (int) $newestAt->diffInHours(now());
        $oldestAgeDays = (int) $oldestAt->diffInDays(now());

        $messages = [];
        $status = 'ok';

        // Stale: the schedule takes one a day, so more than 48 hours means two
        // runs have been missed, not one late run.
        if ($ageHours >= 48) {
            $status = 'stale';
            $messages[] = "The most recent backup is {$this->days($ageHours)} old. The daily schedule may not be running.";
        }

        // Eviction can only have happened if the set is actually pressing the
        // cap: the cleanup deletes oldest-first until the total fits, so a set
        // comfortably under the cap has lost nothing. Checking the cap first
        // matters, because on a newly installed system every copy is younger
        // than the retention period simply because the system is younger than
        // the retention period, and warning on that alone cries wolf on day one.
        $nearCap = $capMb && $total >= ($capMb * 1024 * 1024) * 0.9;

        if ($nearCap) {
            $shortHistory = $retentionDays > 0 && count($files) > 1 && $oldestAgeDays < $retentionDays;

            if ($shortHistory) {
                $status = $status === 'stale' ? 'stale' : 'truncated';
                $messages[] = "Retention is set to keep every backup for {$retentionDays} days, but the oldest copy on disk is only {$oldestAgeDays} day"
                    .($oldestAgeDays === 1 ? '' : 's')
                    .' old. Older backups are being deleted early to stay under the size cap, so the restore window is shorter than it looks.';
            } else {
                $status = $status === 'stale' ? 'stale' : 'truncated';
                $messages[] = 'The backup set is within 10% of its size cap. The next run will start deleting the oldest copies.';
            }
        }

        return [
            'configured' => true,
            'last' => [
                'at' => $newestAt->toIso8601String(),
                'age_hours' => $ageHours,
                'size_bytes' => $newest['size'],
                'size_human' => $this->human($newest['size']),
            ],
            'copies' => count($files),
            'total_bytes' => $total,
            'total_human' => $this->human($total),
            'cap_megabytes' => $capMb,
            'used_percent' => $capMb ? (int) round($total / ($capMb * 1024 * 1024) * 100) : null,
            'retention_days' => $retentionDays,
            'oldest_at' => $oldestAt->toIso8601String(),
            'oldest_age_days' => $oldestAgeDays,
            'status' => $status,
            'messages' => $messages,
        ];
    }

    /**
     * Backup zips on the destination disk, oldest first.
     *
     * @return array<int, array{path: string, size: int, time: int}>
     */
    protected function backupFiles(): array
    {
        $disk = Storage::disk($this->disk);

        if (! $disk->exists($this->name)) {
            return [];
        }

        $files = [];
        foreach ($disk->files($this->name) as $path) {
            if (! str_ends_with(strtolower($path), '.zip')) {
                continue;
            }
            $files[] = [
                'path' => $path,
                'size' => (int) $disk->size($path),
                'time' => (int) $disk->lastModified($path),
            ];
        }

        usort($files, fn ($a, $b) => $a['time'] <=> $b['time']);

        return $files;
    }

    protected function human(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1).' MB';
        }

        return round($bytes / 1024 / 1024 / 1024, 2).' GB';
    }

    protected function days(int $hours): string
    {
        if ($hours < 48) {
            return $hours.' hours';
        }

        return intdiv($hours, 24).' days';
    }
}
