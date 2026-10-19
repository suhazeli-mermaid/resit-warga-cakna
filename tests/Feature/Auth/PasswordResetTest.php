<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_public_reset_request_does_not_reveal_whether_an_email_exists(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $message = 'Jika akaun sepadan ditemui, pautan tukar kata laluan akan dihantar ke alamat e-mel tersebut.';

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', $message);

        $this->post('/forgot-password', ['email' => 'tiada@example.com'])
            ->assertSessionHas('status', $message);
    }

    public function test_public_reset_response_stays_generic_when_email_delivery_is_unavailable(): void
    {
        Notification::fake();
        config()->set('mail.default', 'log');

        $user = User::factory()->create();
        $message = 'Jika akaun sepadan ditemui, pautan tukar kata laluan akan dihantar ke alamat e-mel tersebut.';

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', $message);

        $this->post('/forgot-password', ['email' => 'tiada@example.com'])
            ->assertSessionHas('status', $message);

        Notification::assertNothingSent();
    }

    public function test_authenticated_user_can_request_a_link_only_for_their_registered_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->post('/account/password-reset-link', ['email' => $otherUser->email])
            ->assertRedirect('/profile')
            ->assertSessionHas('status', 'password-reset-link-sent');

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertNotSentTo($otherUser, ResetPassword::class);
    }

    public function test_authenticated_reset_link_requests_are_rate_limited(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($user)->post('/account/password-reset-link')->assertRedirect('/profile');
        }

        $this->actingAs($user)->post('/account/password-reset-link')->assertTooManyRequests();
    }

    public function test_log_mailer_is_rejected_so_reset_tokens_are_not_logged(): void
    {
        Notification::fake();
        config()->set('mail.default', 'log');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/account/password-reset-link')
            ->assertRedirect('/profile')
            ->assertSessionHasErrors('email', null, 'passwordReset');

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_reset_password_screen_can_be_rendered_while_authenticated(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->actingAs($user)
                ->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))
                ->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $this->assertTrue(Hash::check('password', $user->refresh()->password));

            return true;
        });
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $payload = [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'kata-laluan-baharu',
                'password_confirmation' => 'kata-laluan-baharu',
            ];

            $this->post('/reset-password', $payload)->assertRedirect(route('login'));
            $this->post('/reset-password', $payload)->assertSessionHasErrors('email');

            return true;
        });
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->travel(((int) config('auth.passwords.users.expire')) + 1)->minutes();

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'kata-laluan-baharu',
                'password_confirmation' => 'kata-laluan-baharu',
            ])->assertSessionHasErrors('email');

            return true;
        });
    }

    public function test_user_can_log_in_with_the_new_password_after_reset(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'kata-laluan-baharu',
                'password_confirmation' => 'kata-laluan-baharu',
            ])->assertRedirect(route('login'));

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'kata-laluan-baharu',
            ])->assertRedirect(route('dashboard', absolute: false));

            $this->assertAuthenticatedAs($user->refresh());

            return true;
        });
    }

    public function test_successful_reset_invalidates_database_sessions_for_the_user(): void
    {
        Notification::fake();
        config()->set('session.driver', 'database');

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        DB::table('sessions')->insert([
            [
                'id' => 'session-for-reset-user',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => '',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'session-for-other-user',
                'user_id' => $otherUser->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => '',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $otherUser) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'kata-laluan-baharu',
                'password_confirmation' => 'kata-laluan-baharu',
            ])->assertRedirect(route('login'));

            $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
            $this->assertDatabaseHas('sessions', ['user_id' => $otherUser->id]);

            return true;
        });
    }
}
