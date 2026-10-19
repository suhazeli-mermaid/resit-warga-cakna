<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NavigationAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_navigation_contains_accessible_account_menus_in_required_order(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response
            ->assertOk()
            ->assertSee('aria-controls="account-menu"', false)
            ->assertSee(':aria-expanded="open.toString()"', false)
            ->assertSee('@click.outside="open = false"', false)
            ->assertSee('@keydown.escape.window', false)
            ->assertSee('id="mobile-navigation"', false)
            ->assertSee('id="mobile-account-menu"', false)
            ->assertSee('action="'.route('account.password.email').'"', false)
            ->assertSee('action="'.route('logout').'"', false);

        $content = $response->getContent();
        $profilePosition = strpos($content, 'Profil Pengguna');
        $passwordPosition = strpos($content, 'Tukar Kata Laluan');
        $logoutPosition = strpos($content, 'Logout');

        $this->assertNotFalse($profilePosition);
        $this->assertNotFalse($passwordPosition);
        $this->assertNotFalse($logoutPosition);
        $this->assertLessThan($passwordPosition, $profilePosition);
        $this->assertLessThan($logoutPosition, $passwordPosition);
    }

    public function test_account_routes_and_menu_require_authentication(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->post('/account/password-reset-link')->assertRedirect('/login');
    }

    public function test_logout_is_post_only_and_navigation_supplies_a_csrf_token(): void
    {
        $user = User::factory()->create();
        $logoutRoute = Route::getRoutes()->getByName('logout');

        $this->assertSame(['POST'], $logoutRoute->methods());
        $this->get('/logout')->assertMethodNotAllowed();

        $this->actingAs($user)
            ->get('/profile')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('name="_token"', false);

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_existing_campaign_receipt_and_payment_routes_remain_registered(): void
    {
        foreach ([
            'public.index',
            'dana.show',
            'dana.information',
            'public.donate.process',
            'payment.return',
            'payment.callback',
            'receipts.index',
            'receipts.create',
            'receipts.store',
            'admin.cms.create',
            'admin.cms.store',
            'admin.cms.edit',
        ] as $routeName) {
            $this->assertNotNull(Route::getRoutes()->getByName($routeName), "Route [{$routeName}] is missing.");
        }
    }
}
