<?php

namespace Tests\Feature\Documents;

use App\Models\Aircraft;
use App\Models\Part;
use App\Models\PartBatch;
use App\Models\PartSerial;
use App\Models\Requisition;
use App\Models\Siv;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\Stock\StockService;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SivTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(StoreSeeder::class);
        $this->seed(DocumentCounterSeeder::class);
        $this->user = User::factory()->create()->assignRole('Storekeeper'); // issues.post
    }

    protected function store(string $type): Store
    {
        return Store::where('type', $type)->firstOrFail();
    }

    protected function stock(): StockService
    {
        return app(StockService::class);
    }

    /** Build a draft SIV with items directly, then return it. */
    protected function draftSiv(array $items, array $header = []): Siv
    {
        $siv = Siv::factory()->create($header);
        foreach ($items as $n => $item) {
            $siv->items()->create(array_merge([
                'line_no' => $n + 1,
                'qty_required' => 1,
                'qty_issued' => 1,
                'source_store_id' => $this->store('bonded')->id,
            ], $item));
        }

        return $siv->load(['items.part', 'items.sourceStore', 'items.requisition']);
    }

    public function test_issuing_against_an_approved_requisition_marks_it_issued(): void
    {
        $part = Part::factory()->create();
        $this->stock()->openingBalance($part, $this->store('bonded'), 5, $this->user);
        $req = Requisition::factory()->approved()->create(['part_id' => $part->id]);

        $siv = $this->draftSiv([[
            'part_id' => $part->id, 'requisition_id' => $req->id, 'qty_required' => 1, 'qty_issued' => 1,
        ]]);

        $this->actingAs($this->user)->post("/issuing/{$siv->id}/post")->assertRedirect();

        $this->assertSame('issued', $req->fresh()->status);
        $this->assertEquals(4, StockBalance::where('part_id', $part->id)
            ->where('store_id', $this->store('bonded')->id)->value('quantity'));
    }

    public function test_partial_issue_leaves_the_requisition_approved(): void
    {
        $part = Part::factory()->create();
        $this->stock()->openingBalance($part, $this->store('bonded'), 5, $this->user);
        $req = Requisition::factory()->approved()->create(['part_id' => $part->id]);

        // Need 2, only 1 issued now.
        $siv = $this->draftSiv([[
            'part_id' => $part->id, 'requisition_id' => $req->id, 'qty_required' => 2, 'qty_issued' => 1,
        ]]);

        $this->actingAs($this->user)->post("/issuing/{$siv->id}/post")->assertRedirect();

        $this->assertSame('approved', $req->fresh()->status);
        $this->assertEquals(4, StockBalance::where('part_id', $part->id)
            ->where('store_id', $this->store('bonded')->id)->value('quantity'));
    }

    public function test_a_standalone_consumable_line_issues_without_a_requisition(): void
    {
        $part = Part::factory()->create();
        $this->stock()->openingBalance($part, $this->store('bonded'), 10, $this->user);

        $siv = $this->draftSiv([[
            'part_id' => $part->id, 'requisition_id' => null, 'qty_required' => 3, 'qty_issued' => 3,
        ]]);

        $this->actingAs($this->user)->post("/issuing/{$siv->id}/post")->assertRedirect();

        $this->assertEquals(7, StockBalance::where('part_id', $part->id)
            ->where('store_id', $this->store('bonded')->id)->value('quantity'));
    }

    public function test_issuing_follows_fefo_batch_order(): void
    {
        $part = Part::factory()->shelfLife()->create();
        $bonded = $this->store('bonded');
        $late = PartBatch::create(['part_id' => $part->id, 'batch_number' => 'LATE', 'expiry_date' => '2027-12-31', 'qty_received' => 5]);
        $early = PartBatch::create(['part_id' => $part->id, 'batch_number' => 'EARLY', 'expiry_date' => '2026-09-30', 'qty_received' => 5]);
        $this->stock()->openingBalance($part, $bonded, 5, $this->user, batchId: $late->id);
        $this->stock()->openingBalance($part, $bonded, 5, $this->user, batchId: $early->id);

        // No batch chosen → SivService uses FEFO (earliest expiry = EARLY).
        $siv = $this->draftSiv([['part_id' => $part->id, 'qty_required' => 2, 'qty_issued' => 2]]);
        $this->actingAs($this->user)->post("/issuing/{$siv->id}/post")->assertRedirect();

        $issue = StockMovement::where('part_id', $part->id)->where('movement_type', 'issue')->latest('id')->first();
        $this->assertSame($early->id, $issue->part_batch_id);
    }

    public function test_serialized_issue_installs_the_serial_on_the_requisition_aircraft(): void
    {
        $part = Part::factory()->serialized()->create();
        $bonded = $this->store('bonded');
        $serial = PartSerial::factory()->create(['part_id' => $part->id, 'status' => 'in_store', 'current_store_id' => $bonded->id]);
        $this->stock()->openingBalance($part, $bonded, 1, $this->user, serialId: $serial->id);

        $aircraft = Aircraft::factory()->create();
        $req = Requisition::factory()->approved()->create(['part_id' => $part->id, 'aircraft_id' => $aircraft->id]);

        $siv = $this->draftSiv([[
            'part_id' => $part->id, 'requisition_id' => $req->id,
            'qty_required' => 1, 'qty_issued' => 1, 'serial_ids' => [$serial->id],
        ]]);

        $this->actingAs($this->user)->post("/issuing/{$siv->id}/post")->assertRedirect();

        $serial->refresh();
        $this->assertSame('installed', $serial->status);
        $this->assertSame($aircraft->id, $serial->current_aircraft_id);
        $this->assertSame('issued', $req->fresh()->status);
    }

    public function test_issues_are_blocked_from_non_bonded_dope_stores(): void
    {
        $part = Part::factory()->create();
        $this->stock()->openingBalance($part, $this->store('bonded'), 5, $this->user);

        $siv = $this->draftSiv([[
            'part_id' => $part->id, 'qty_required' => 1, 'qty_issued' => 1,
            'source_store_id' => $this->store('quarantine')->id,
        ]]);

        $this->actingAs($this->user)->post("/issuing/{$siv->id}/post")
            ->assertSessionHasErrors('items.0.source_store_id');
        $this->assertSame('draft', $siv->fresh()->status);
    }

    public function test_serialized_lines_require_matching_serial_selection(): void
    {
        $part = Part::factory()->serialized()->create();
        $siv = $this->draftSiv([[
            'part_id' => $part->id, 'qty_required' => 2, 'qty_issued' => 2, 'serial_ids' => [],
        ]]);

        $this->actingAs($this->user)->post("/issuing/{$siv->id}/post")
            ->assertSessionHasErrors('items.0.serial_ids');
    }

    public function test_a_posted_siv_is_immutable(): void
    {
        $part = Part::factory()->create();
        $this->stock()->openingBalance($part, $this->store('bonded'), 5, $this->user);
        $siv = $this->draftSiv([['part_id' => $part->id, 'qty_required' => 1, 'qty_issued' => 1]]);
        $this->actingAs($this->user)->post("/issuing/{$siv->id}/post")->assertRedirect();

        $this->actingAs($this->user)->post("/issuing/{$siv->id}/post")->assertStatus(422);
    }

    public function test_posting_requires_the_issues_post_permission(): void
    {
        $viewer = User::factory()->create()->assignRole('Viewer'); // issues.view only
        $siv = Siv::factory()->create();

        $this->actingAs($viewer)->post("/issuing/{$siv->id}/post")->assertForbidden();
    }
}
