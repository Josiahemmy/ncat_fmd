<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Admin\BackupHealth;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The backup set is trimmed by `delete_oldest_backups_when_using_more_megabytes_than`
 * without telling anyone. These cover the two things that matter: only a user
 * holding `backups.view` ever receives the figures, and the panel actually says
 * something when history is being discarded early.
 */
class BackupHealthTest extends TestCase
{
    use RefreshDatabase;

    protected string $backupName = 'NCAT Test Backups';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        Storage::fake('local');
        config()->set('backup.backup.name', $this->backupName);
        config()->set('backup.backup.destination.disks', ['local']);
    }

    /** Write a zip of a given size, aged by a number of days. */
    protected function backupAged(int $daysOld, int $bytes = 1024): void
    {
        $disk = Storage::disk('local');
        $path = $this->backupName.'/'.now()->subDays($daysOld)->format('Y-m-d-H-i-s').'.zip';
        $disk->put($path, str_repeat('0', $bytes));
        touch($disk->path($path), now()->subDays($daysOld)->getTimestamp());
    }

    protected function health(): array
    {
        return (new BackupHealth('local', $this->backupName))->report();
    }

    public function test_it_reports_when_no_backup_has_ever_run(): void
    {
        $report = $this->health();

        $this->assertSame('never_run', $report['status']);
        $this->assertNull($report['last']);
        $this->assertSame(0, $report['copies']);
    }

    public function test_a_current_backup_set_reads_as_healthy(): void
    {
        config()->set('backup.cleanup.default_strategy.keep_all_backups_for_days', 14);
        config()->set('backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than', 5000);

        $this->backupAged(20);
        $this->backupAged(1);

        $report = $this->health();

        $this->assertSame('ok', $report['status'], 'A set with history older than retention is not being trimmed.');
        $this->assertSame(2, $report['copies']);
        $this->assertSame([], $report['messages']);
    }

    public function test_it_warns_when_the_cap_is_silently_discarding_history(): void
    {
        // Retention promises 14 days, the set is pressing a 1 MB cap, and every
        // copy on disk is newer than 14 days. Together those can only mean the
        // cap evicted the older ones.
        config()->set('backup.cleanup.default_strategy.keep_all_backups_for_days', 14);
        config()->set('backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than', 1);

        $this->backupAged(3, 400 * 1024);
        $this->backupAged(2, 400 * 1024);
        $this->backupAged(0, 300 * 1024);

        $report = $this->health();

        $this->assertSame('truncated', $report['status']);
        $this->assertStringContainsString('restore window is shorter', implode(' ', $report['messages']));
    }

    /**
     * The day-one case. A system installed this week has no history older than
     * the retention period because it has not existed that long, which must not
     * read as the cap discarding backups.
     */
    public function test_a_new_install_well_under_the_cap_is_not_reported_as_truncated(): void
    {
        config()->set('backup.cleanup.default_strategy.keep_all_backups_for_days', 14);
        config()->set('backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than', 5000);

        $this->backupAged(2, 1024);
        $this->backupAged(1, 1024);
        $this->backupAged(0, 1024);

        $report = $this->health();

        $this->assertSame('ok', $report['status'], 'A young backup set well under the cap was misreported as trimmed.');
        $this->assertSame([], $report['messages']);
    }

    public function test_it_warns_when_the_most_recent_backup_is_stale(): void
    {
        config()->set('backup.cleanup.default_strategy.keep_all_backups_for_days', 14);

        $this->backupAged(30);
        $this->backupAged(5);

        $report = $this->health();

        $this->assertSame('stale', $report['status']);
        $this->assertStringContainsString('daily schedule', implode(' ', $report['messages']));
    }

    public function test_the_dashboard_withholds_the_figures_without_the_permission(): void
    {
        $this->backupAged(1);

        $user = User::factory()->create();
        $user->givePermissionTo('users.view');

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('backupHealth', null));
    }

    public function test_the_dashboard_shows_the_figures_with_the_permission(): void
    {
        $this->backupAged(1, 2048);

        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('backupHealth.copies', 1)
                ->where('backupHealth.total_bytes', 2048)
                ->has('backupHealth.status'));
    }
}
