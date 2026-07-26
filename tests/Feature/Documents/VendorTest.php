<?php

namespace Tests\Feature\Documents;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Vendors module (spec §12.4). */
class VendorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    protected function manager(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['vendors.view', 'vendors.manage']);

        return $user;
    }

    protected function page(User $as, string $uri)
    {
        return $this->actingAs($as)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ])->get($uri);
    }

    public function test_the_index_needs_the_view_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('vendors.index'))->assertForbidden();

        $this->page($this->manager(), route('vendors.index'))->assertOk();
    }

    public function test_a_vendor_is_created_from_the_index_form(): void
    {
        $this->actingAs($this->manager())->post(route('vendors.store'), [
            'name' => 'DIAMOND AIRCRAFT INDUSTRIES GMBH',
            'type' => 'supplier',
            'address' => "DIAMOND AIRCRAFT INDUSTRIES GMBH,\nNIKOLAUS-AUGUST-OTTO-STRAUBE 5,\n2700WIENERNEUSTADT,\nAUSTRIA.",
            'country' => 'Austria',
            'is_active' => true,
        ])->assertRedirect(route('vendors.index'))->assertSessionHasNoErrors();

        $vendor = Vendor::firstOrFail();

        $this->assertSame('supplier', $vendor->type);
        $this->assertFalse($vendor->canRepair());
        $this->assertSame([
            'DIAMOND AIRCRAFT INDUSTRIES GMBH,',
            'NIKOLAUS-AUGUST-OTTO-STRAUBE 5,',
            '2700WIENERNEUSTADT,',
            'AUSTRIA.',
        ], $vendor->addressLines());
    }

    public function test_the_list_filters_by_type_country_and_active_and_searches(): void
    {
        Vendor::factory()->create(['name' => 'ALPHA SUPPLIES', 'country' => 'Nigeria']);
        Vendor::factory()->repairOrganization()->create(['name' => 'BRINKLEY AEROSPACE', 'country' => 'England']);
        Vendor::factory()->inactive()->create(['name' => 'DORMANT LTD', 'country' => 'Nigeria']);

        $manager = $this->manager();

        $names = fn (array $query) => collect(
            $this->page($manager, route('vendors.index', $query))->json('props.vendors')
        )->pluck('name')->all();

        $this->assertSame(['BRINKLEY AEROSPACE'], $names(['type' => 'repair_organization']));
        $this->assertSame(['ALPHA SUPPLIES', 'DORMANT LTD'], $names(['country' => 'Nigeria']));
        $this->assertSame(['DORMANT LTD'], $names(['active' => 'inactive']));
        $this->assertSame(['BRINKLEY AEROSPACE'], $names(['search' => 'brinkley']));
    }

    public function test_a_vendor_with_orders_cannot_be_deleted(): void
    {
        $vendor = Vendor::factory()->create();
        PurchaseOrder::factory()->create(['vendor_id' => $vendor->id]);

        $this->actingAs($this->manager())
            ->delete(route('vendors.destroy', $vendor))
            ->assertSessionHasErrors('vendor');

        $this->assertNotSoftDeleted($vendor);
    }

    public function test_a_vendor_without_orders_can_be_deleted(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($this->manager())
            ->delete(route('vendors.destroy', $vendor))
            ->assertRedirect(route('vendors.index'));

        $this->assertSoftDeleted($vendor);
    }

    public function test_the_detail_page_lists_the_vendors_orders(): void
    {
        $vendor = Vendor::factory()->both()->create();
        PurchaseOrder::factory()->issued()->create(['vendor_id' => $vendor->id]);
        \App\Models\RepairOrder::factory()->issued()->create(['vendor_id' => $vendor->id]);

        $this->page($this->manager(), route('vendors.show', $vendor))
            ->assertOk()
            ->assertJsonPath('component', 'Vendors/Show')
            ->assertJsonPath('props.vendor.can_repair', true)
            ->assertJsonCount(1, 'props.purchaseOrders')
            ->assertJsonCount(1, 'props.repairOrders');
    }
}
