<?php

namespace Tests\Feature\Stock;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Store-page drill-down actions (spec §12.3): tally everywhere, plus scoped
 * "raise requisition" and "raise issue" on every store except Quarantine.
 */
class StorePageActionsTest extends TestCase
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

    protected function store(string $type): Store
    {
        return Store::where('type', $type)->firstOrFail();
    }

    protected function page(string $uri)
    {
        return $this->actingAs($this->officer)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ])->get($uri);
    }

    public function test_a_serviceable_store_allows_both_document_actions(): void
    {
        $bonded = $this->store('bonded');

        $this->page(route('stores.show', $bonded->id))
            ->assertOk()
            ->assertJsonPath('props.store.allows_documents', true)
            ->assertJsonPath('props.store.allows_issue', true);
    }

    public function test_quarantine_stays_view_only(): void
    {
        $this->page(route('stores.show', $this->store('quarantine')->id))
            ->assertOk()
            ->assertJsonPath('props.store.allows_documents', false)
            ->assertJsonPath('props.store.allows_issue', false);
    }

    public function test_the_fuel_store_allows_a_requisition_but_not_a_stores_issue(): void
    {
        // Fuel moves through the fuel posting screens, not the SIV.
        $this->page(route('stores.show', $this->store('fuel')->id))
            ->assertOk()
            ->assertJsonPath('props.store.allows_documents', true)
            ->assertJsonPath('props.store.allows_issue', false);
    }

    public function test_raising_a_requisition_from_a_store_pre_fills_that_store_as_the_supply_source(): void
    {
        $dope = $this->store('dope');
        $engineer = User::factory()->create()->assignRole('Engineer/Technician');

        $this->actingAs($engineer)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ])->get(route('requisitions.create', ['store' => $dope->id]))
            ->assertOk()
            ->assertJsonPath('props.originStore.id', $dope->id)
            ->assertJsonPath('props.originStore.name', $dope->name);
    }

    public function test_quarantine_cannot_be_used_as_a_requisition_origin(): void
    {
        $engineer = User::factory()->create()->assignRole('Engineer/Technician');

        $this->actingAs($engineer)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ])->get(route('requisitions.create', ['store' => $this->store('quarantine')->id]))
            ->assertOk()
            ->assertJsonPath('props.originStore', null);
    }

    public function test_a_tally_card_can_be_opened_scoped_to_a_store(): void
    {
        $part = \App\Models\Part::factory()->create();
        $bonded = $this->store('bonded');

        app(\App\Services\Stock\StockService::class)
            ->openingBalance($part, $bonded, 5, $this->officer);

        $this->page(route('tally-cards.show', ['part' => $part->id, 'store' => $bonded->id]))
            ->assertOk()
            ->assertJsonPath('props.currentStore.id', $bonded->id)
            ->assertJsonPath('props.filters.store', $bonded->id);
    }

    // ---- Loan holding stores are system locations (spec §12.7) -----------

    /**
     * What sits in these two stores got there through the loan register, and
     * only a return takes it out again. Offering a requisition or an issue
     * would let a clerk move it without touching the loan that explains it.
     */
    protected function assertOffersNoDocumentActions(string $type): void
    {
        $this->page(route('stores.show', $this->store($type)->id))
            ->assertOk()
            ->assertJsonPath('props.store.is_system', true)
            ->assertJsonPath('props.store.allows_documents', false)
            ->assertJsonPath('props.store.allows_issue', false);
    }

    public function test_the_stock_lent_out_store_offers_no_document_actions(): void
    {
        $this->assertOffersNoDocumentActions(Store::LOAN_OUT);
    }

    public function test_the_stock_borrowed_in_store_offers_no_document_actions(): void
    {
        $this->assertOffersNoDocumentActions(Store::LOAN_IN);
    }

    /** A hand transfer must not be able to walk stock into a loan store. */
    public function test_system_locations_are_not_offered_as_transfer_destinations(): void
    {
        $response = $this->page(route('stores.show', $this->store('bonded')->id))->assertOk();

        $offered = collect($response->json('props.transferTargets'))->pluck('name');

        foreach ([Store::LOAN_OUT, Store::LOAN_IN, 'quarantine'] as $type) {
            $this->assertNotContains(
                $this->store($type)->name,
                $offered,
                "{$type} is a system location and must not be a transfer destination.",
            );
        }

        $this->assertContains($this->store('dope')->name, $offered, 'Real stores must still be offered.');
    }
}
