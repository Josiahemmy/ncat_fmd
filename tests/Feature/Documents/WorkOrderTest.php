<?php

namespace Tests\Feature\Documents;

use App\Models\Aircraft;
use App\Models\AircraftType;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(DocumentCounterSeeder::class);
    }

    protected function engineer(): User
    {
        return User::factory()->create()->assignRole('Engineer/Technician');
    }

    public function test_creating_a_work_order_reserves_a_formatted_ref(): void
    {
        $type = AircraftType::factory()->create(['wo_code' => 'DA40NG']);
        $aircraft = Aircraft::factory()->create(['aircraft_type_id' => $type->id]);

        $this->actingAs($this->engineer())->post('/work-orders', [
            'aircraft_id' => $aircraft->id,
            'work_type' => 'snag',
            'title' => 'SNAG: Nose wheel tyre worn out',
            'raised_by' => 'Albert',
            'work_date' => '2026-05-15',
        ])->assertRedirect();

        $wo = WorkOrder::first();
        $this->assertSame('FMD/DA40NG/05/26/1344', $wo->wo_ref);
        $this->assertSame('open', $wo->status);
        $this->assertSame('snag', $wo->work_type);
    }

    public function test_scheduled_inspection_requires_an_inspection_type(): void
    {
        $aircraft = Aircraft::factory()->create();

        $this->actingAs($this->engineer())->post('/work-orders', [
            'aircraft_id' => $aircraft->id,
            'work_type' => 'scheduled_inspection',
            'title' => 'Inspection',
            'raised_by' => 'Albert',
            'work_date' => '2026-05-15',
        ])->assertSessionHasErrors('inspection_type');
    }

    public function test_status_transitions_open_to_in_progress_to_closed(): void
    {
        $wo = WorkOrder::factory()->create(['status' => 'open']);
        $editor = User::factory()->create();
        $editor->givePermissionTo(['work_orders.view', 'work_orders.edit']);

        $payload = [
            'aircraft_id' => $wo->aircraft_id,
            'work_type' => $wo->work_type,
            'title' => $wo->title,
            'raised_by' => $wo->raised_by,
            'work_date' => $wo->work_date->toDateString(),
        ];

        $this->actingAs($editor)->put("/work-orders/{$wo->id}", $payload + ['status' => 'in_progress'])
            ->assertRedirect();
        $this->assertSame('in_progress', $wo->fresh()->status);
        $this->assertNull($wo->fresh()->closed_at);

        $this->actingAs($editor)->put("/work-orders/{$wo->id}", $payload + ['status' => 'closed'])
            ->assertRedirect();
        $this->assertSame('closed', $wo->fresh()->status);
        $this->assertNotNull($wo->fresh()->closed_at);
    }

    public function test_creating_requires_the_create_permission(): void
    {
        $aircraft = Aircraft::factory()->create();

        // Viewer has work_orders.view but not create.
        $viewer = User::factory()->create()->assignRole('Viewer');

        $this->actingAs($viewer)->post('/work-orders', [
            'aircraft_id' => $aircraft->id,
            'work_type' => 'other',
            'title' => 'x',
            'raised_by' => 'y',
            'work_date' => '2026-05-15',
        ])->assertForbidden();
    }

    public function test_register_requires_the_view_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/work-orders')->assertForbidden();
        $this->actingAs($this->engineer())->get('/work-orders')->assertOk();
    }
}
