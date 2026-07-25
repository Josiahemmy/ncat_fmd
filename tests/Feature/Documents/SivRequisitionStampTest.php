<?php

namespace Tests\Feature\Documents;

use App\Models\Part;
use App\Models\Requisition;
use App\Models\Siv;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SIV "Requisition for" / "Ordered by" stamping (spec §12.2). The ordering
 * officer and request date are derived from the linked requisition on the
 * server; the form renders them read-only, but that is presentation only and the
 * server never trusts what comes back.
 */
class SivRequisitionStampTest extends TestCase
{
    use RefreshDatabase;

    protected User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(StoreSeeder::class);
        $this->seed(DocumentCounterSeeder::class);
        $this->officer = User::factory()->create()->assignRole('Stores Officer');
    }

    protected function approvedRequisition(): Requisition
    {
        $requester = User::factory()->create(['name' => 'Amina Bello'])->assignRole('Engineer/Technician');

        return Requisition::factory()->approved()->create([
            'requested_by_user_id' => $requester->id,
            'submitted_at' => '2026-07-20 09:15:00',
            'full_description' => 'Main wheel tyre',
        ]);
    }

    /** @return array<string, mixed> */
    protected function payload(array $overrides = []): array
    {
        $part = Part::factory()->create();

        return array_replace([
            'items' => [[
                'part_id' => $part->id,
                'qty_required' => 1,
                'qty_issued' => 1,
                'source_store_id' => Store::where('type', 'bonded')->value('id'),
            ]],
        ], $overrides);
    }

    public function test_linking_a_requisition_stamps_the_ordering_officer_and_request_date(): void
    {
        $r = $this->approvedRequisition();

        $this->actingAs($this->officer)->post('/issuing', $this->payload([
            'requisition_id' => $r->id,
        ]))->assertRedirect();

        $siv = Siv::firstOrFail();

        $this->assertSame($r->id, $siv->requisition_id);
        $this->assertSame('Amina Bello', $siv->ordered_by);
        $this->assertSame('2026-07-20', $siv->ordered_by_date->toDateString());
        $this->assertStringContainsString($r->requisition_no, $siv->requisition_for);
        $this->assertStringContainsString('Main wheel tyre', $siv->requisition_for);
    }

    public function test_client_supplied_ordering_details_are_discarded_when_a_requisition_is_linked(): void
    {
        $r = $this->approvedRequisition();

        $this->actingAs($this->officer)->post('/issuing', $this->payload([
            'requisition_id' => $r->id,
            'ordered_by' => 'Someone Else Entirely',
            'ordered_by_date' => '2001-01-01',
            'requisition_for' => 'A made up caption',
        ]))->assertRedirect();

        $siv = Siv::firstOrFail();

        $this->assertSame('Amina Bello', $siv->ordered_by);
        $this->assertSame('2026-07-20', $siv->ordered_by_date->toDateString());
        $this->assertStringNotContainsString('made up', $siv->requisition_for);
    }

    public function test_tampering_is_also_refused_on_update(): void
    {
        $r = $this->approvedRequisition();
        $siv = Siv::factory()->create(['requisition_id' => $r->id, 'ordered_by' => 'Amina Bello']);

        $this->actingAs($this->officer)->put("/issuing/{$siv->id}", $this->payload([
            'requisition_id' => $r->id,
            'ordered_by' => 'Injected Name',
            'ordered_by_date' => '1999-12-31',
        ]))->assertRedirect();

        $this->assertSame('Amina Bello', $siv->fresh()->ordered_by);
        $this->assertSame('2026-07-20', $siv->fresh()->ordered_by_date->toDateString());
    }

    public function test_a_requisition_that_is_not_fully_approved_cannot_be_linked(): void
    {
        $submitted = Requisition::factory()->submitted()->create();
        $issued = Requisition::factory()->issued()->create();

        $this->actingAs($this->officer)->post('/issuing', $this->payload(['requisition_id' => $submitted->id]))
            ->assertSessionHasErrors('requisition_id');

        $this->actingAs($this->officer)->post('/issuing', $this->payload(['requisition_id' => $issued->id]))
            ->assertSessionHasErrors('requisition_id');

        $this->assertSame(0, Siv::count());
    }

    public function test_a_standalone_voucher_keeps_manual_ordering_details(): void
    {
        $this->actingAs($this->officer)->post('/issuing', $this->payload([
            'ordered_by' => 'Captain Idris',
            'ordered_by_date' => '2026-07-24',
            'requisition_for' => 'Hangar consumables',
        ]))->assertRedirect();

        $siv = Siv::firstOrFail();

        $this->assertNull($siv->requisition_id);
        $this->assertSame('Captain Idris', $siv->ordered_by);
        $this->assertSame('2026-07-24', $siv->ordered_by_date->toDateString());
        $this->assertSame('Hangar consumables', $siv->requisition_for);
    }

    public function test_the_create_screen_offers_the_picker_with_the_requesters_name(): void
    {
        $r = $this->approvedRequisition();

        $this->actingAs($this->officer)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ])->get('/issuing/create')->assertOk()
            ->assertJsonPath('props.approvedRequisitions.0.requisition_no', $r->requisition_no)
            ->assertJsonPath('props.approvedRequisitions.0.ordered_by', 'Amina Bello')
            ->assertJsonPath('props.approvedRequisitions.0.ordered_by_date', '2026-07-20');
    }

    public function test_the_create_screen_pre_scopes_lines_to_a_store_when_launched_from_one(): void
    {
        $bonded = Store::where('type', 'bonded')->firstOrFail();

        $this->actingAs($this->officer)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ])->get("/issuing/create?store={$bonded->id}")->assertOk()
            ->assertJsonPath('props.storeId', $bonded->id);
    }
}
