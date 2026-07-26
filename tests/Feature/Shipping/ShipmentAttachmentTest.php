<?php

namespace Tests\Feature\Shipping;

use App\Models\ShipmentEventAttachment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Demo\DemoBackup;
use App\Services\Shipping\ShipmentService;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Attachments on shipment events (Phase 9, item 2).
 *
 * The property that matters most here is that the file is never reachable
 * without the gate. On cPanel shared hosting anything under the document root
 * is fetchable by URL, so these live under `storage/app` and the download route
 * is the only door.
 */
class ShipmentAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(StoreSeeder::class);
        $this->seed(DocumentCounterSeeder::class);
        Storage::fake('local');

        // demo:purge takes a safety backup first, and the real one shells out to
        // a dump tool. Under sqlite that is a cheap file copy, but on the MySQL
        // CI gate it runs mysqldump, which blocks with no timeout configured and
        // hangs the suite rather than failing it. DemoPurgeTest owns the
        // backup-abort behaviour; what is under test here is that the purge
        // clears attachment rows and their files, so stub the backup out.
        $this->app->bind(DemoBackup::class, fn () => new class extends DemoBackup
        {
            public function run(): bool
            {
                return true;
            }
        });
    }

    protected function clerk(): User
    {
        return tap(User::factory()->create())->givePermissionTo(['shipping.view', 'shipping.manage']);
    }

    protected function shipment(User $user)
    {
        return app(ShipmentService::class)->create([
            'vendor_id' => Vendor::factory()->create()->id,
            'description' => 'Two cartons of consumables',
            'expected_arrival_date' => today()->addDays(10)->toDateString(),
            'status' => 'Shipped',
            'event_date' => today()->subDays(5)->toDateString(),
        ], $user);
    }

    protected function recordEventWith(User $user, $shipment, array $files)
    {
        return $this->actingAs($user)->post(route('shipments.events.store', $shipment->id), [
            'status' => 'Cleared customs',
            'event_date' => today()->toDateString(),
            'note' => 'Release note and agent invoice attached.',
            'attachments' => $files,
        ]);
    }

    public function test_files_attach_to_the_event_and_land_outside_the_document_root(): void
    {
        $user = $this->clerk();
        $shipment = $this->shipment($user);

        $this->recordEventWith($user, $shipment, [
            UploadedFile::fake()->create('airway-bill.pdf', 400, 'application/pdf'),
            UploadedFile::fake()->image('customs-release.jpg'),
        ])->assertRedirect();

        // The events relation is ordered oldest-first, so reach for the row by
        // id rather than taking the first of the relation.
        $event = \App\Models\ShipmentEvent::where('shipment_id', $shipment->id)
            ->orderByDesc('id')->firstOrFail();

        $this->assertCount(2, $event->attachments);

        foreach ($event->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);

            // The stored name is generated, so a crafted upload name cannot
            // steer where the file lands.
            $this->assertStringStartsWith("shipment-events/{$event->id}/", $attachment->path);
            $this->assertStringNotContainsString('..', $attachment->path);
            $this->assertNotSame($attachment->original_name, basename($attachment->path));

            // Nothing may be written under the web root.
            $this->assertStringNotContainsString('public/', $attachment->path);
        }

        $this->assertSame('airway-bill.pdf', $event->attachments->first()->original_name);
    }

    public function test_an_unsupported_file_type_is_refused(): void
    {
        $user = $this->clerk();
        $shipment = $this->shipment($user);

        $this->recordEventWith($user, $shipment, [
            UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, ShipmentEventAttachment::count());
    }

    public function test_a_file_over_the_size_cap_is_refused(): void
    {
        $user = $this->clerk();
        $shipment = $this->shipment($user);

        $this->recordEventWith($user, $shipment, [
            UploadedFile::fake()->create('huge.pdf', ShipmentEventAttachment::MAX_SIZE_KB + 1, 'application/pdf'),
        ])->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, ShipmentEventAttachment::count());
    }

    /**
     * Built without an HTTP request so the guest case below is genuinely a
     * guest: actingAs() would otherwise persist across the whole test.
     */
    protected function attachDirectly(): ShipmentEventAttachment
    {
        $user = $this->clerk();
        $shipment = $this->shipment($user);
        $event = \App\Models\ShipmentEvent::where('shipment_id', $shipment->id)->firstOrFail();

        return app(ShipmentService::class)->attach(
            $event,
            UploadedFile::fake()->create('airway-bill.pdf', 100, 'application/pdf'),
            $user,
        );
    }

    public function test_a_signed_out_visitor_cannot_download_an_attachment(): void
    {
        $url = route('shipments.attachments.download', $this->attachDirectly()->id);

        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_a_signed_in_user_without_shipping_view_cannot_download_an_attachment(): void
    {
        $url = route('shipments.attachments.download', $this->attachDirectly()->id);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_shipping_view_can_download_the_original_file(): void
    {
        $url = route('shipments.attachments.download', $this->attachDirectly()->id);
        $viewer = tap(User::factory()->create())->givePermissionTo('shipping.view');

        $response = $this->actingAs($viewer)->get($url);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'airway-bill.pdf',
            $response->headers->get('content-disposition'),
            'The clerk should get their own filename back, not the generated one.',
        );
    }

    public function test_deleting_the_row_removes_the_file(): void
    {
        $user = $this->clerk();
        $shipment = $this->shipment($user);
        $this->recordEventWith($user, $shipment, [
            UploadedFile::fake()->create('airway-bill.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $attachment = ShipmentEventAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $attachment->delete();

        Storage::disk('local')->assertMissing($attachment->path);
    }

    /**
     * The purge guarantee covers the disk as well as the database. A row that
     * goes without its file leaves an orphan eating shared-hosting quota that
     * nothing in the app can find again.
     */
    public function test_purging_removes_attachment_rows_and_their_files(): void
    {
        $user = $this->clerk();
        $shipment = $this->shipment($user);
        $this->recordEventWith($user, $shipment, [
            UploadedFile::fake()->create('airway-bill.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->image('customs-release.png'),
        ])->assertRedirect();

        $paths = ShipmentEventAttachment::pluck('path');
        $this->assertCount(2, $paths);

        $this->artisan('demo:purge', [
            '--i-understand-this-deletes-all-transactional-data' => true,
            '--no-interaction-confirmed' => true,
        ])->assertSuccessful();

        $this->assertSame(0, ShipmentEventAttachment::count(), 'Attachment rows survived the purge.');

        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }

        $this->assertEmpty(
            Storage::disk('local')->allFiles('shipment-events'),
            'The purge left orphan files on disk.',
        );
    }
}
