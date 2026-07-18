<?php

namespace Tests\Feature\Admin;

use App\Models\Aircraft;
use App\Models\AircraftType;
use App\Models\AtaChapter;
use App\Models\DocumentCounter;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReferenceAndAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_data_seeds_the_expected_counts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6, AircraftType::count());
        $this->assertSame(26, Aircraft::count());
        $this->assertSame(4, Store::count());
        $this->assertGreaterThanOrEqual(40, AtaChapter::count());
        $this->assertSame(4, DocumentCounter::count());
    }

    public function test_stores_have_the_correct_types(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame('quarantine', Store::where('slug', 'quarantine')->value('type'));
        $this->assertSame('bonded', Store::where('slug', 'bonded')->value('type'));
        $this->assertSame('dope', Store::where('slug', 'dope')->value('type'));
        $this->assertSame('fuel', Store::where('slug', 'fuel-dump')->value('type'));
    }

    public function test_work_order_counter_is_seeded_from_the_ledger_not_the_prose_estimate(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Forms win: ledger latest was 1343 → next 1344 (not the prose ~1339).
        $wo = DocumentCounter::where('series', 'work_order')->first();
        $this->assertSame(1344, $wo->next_number);
        $this->assertFalse($wo->confirmed);

        $siv = DocumentCounter::where('series', 'siv')->first();
        $this->assertSame('0294', $siv->peek());
    }

    public function test_seeders_are_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(26, Aircraft::count());
        $this->assertSame(4, Store::count());
        $this->assertSame(1, User::where('email', 'superadmin@ncatfmd.com.ng')->count());
    }

    public function test_deactivated_users_cannot_authenticate(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_records_last_login_timestamp(): void
    {
        $user = User::factory()->create(['last_login_at' => null]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_flagged_users_are_forced_to_change_password_before_anything_else(): void
    {
        $user = User::factory()->create(['password_change_required' => true]);

        // Any protected page redirects to the change-password screen.
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.change'));

        // The change screen itself is reachable.
        $this->actingAs($user)->get(route('password.change'))->assertOk();
    }

    public function test_setting_a_new_password_clears_the_flag(): void
    {
        $user = User::factory()->create(['password_change_required' => true]);

        $this->actingAs($user)->post(route('password.change.update'), [
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->password_change_required);
        $this->assertTrue(Hash::check('NewSecret123', $user->password));
    }

    public function test_weak_passwords_are_rejected_on_forced_change(): void
    {
        $user = User::factory()->create(['password_change_required' => true]);

        $this->actingAs($user)->post(route('password.change.update'), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->password_change_required);
    }
}
