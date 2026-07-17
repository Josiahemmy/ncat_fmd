<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase 0 definition-of-done coverage:
 *  · login works              · guests are redirected
 *  · registration disabled    · dashboard renders for an authed user
 *  · health endpoint is up     · Super Admin seeds correctly
 */
class Phase0FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_a_user_can_authenticate_and_reach_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_guests_are_redirected_to_login_from_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_guests_are_redirected_from_a_module_page(): void
    {
        $this->get('/parts')->assertRedirect('/login');
    }

    public function test_the_root_redirects_into_the_app(): void
    {
        $this->get('/')->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_public_registration_is_disabled(): void
    {
        // The named route must not exist…
        $this->assertFalse(Route::has('register'));

        // …and the path must 404 for both GET and POST.
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_dashboard_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
    }

    public function test_a_module_placeholder_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/work-orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Placeholder')
                ->where('module', 'Work Orders'));
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_super_admin_is_seeded_with_the_super_admin_role(): void
    {
        $this->seed(RolesAndAdminSeeder::class);

        $admin = User::where('email', 'superadmin@ncatfmd.com.ng')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('Super Admin'));
        $this->assertTrue($admin->password_change_required);
    }
}
