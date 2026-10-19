<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_offers_email_verified_password_change_instead_of_current_password_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Tukar Kata Laluan')
            ->assertSee('Hantar Pautan Pengesahan')
            ->assertDontSee('name="current_password"', false)
            ->assertSee('action="'.route('account.password.email').'"', false);
    }

    public function test_legacy_direct_password_update_endpoint_is_not_available(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/password', [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertNotFound();
    }
}
