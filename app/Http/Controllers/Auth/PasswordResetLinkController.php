<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $this->sendResetLinkSafely($request->only('email'));

        // Always return the same public response for known and unknown addresses,
        // including when email delivery is unavailable.
        return back()->with('status', __('Jika akaun sepadan ditemui, pautan tukar kata laluan akan dihantar ke alamat e-mel tersebut.'));
    }

    /**
     * Send a reset link only to the signed-in user's registered email address.
     */
    public function storeForAuthenticatedUser(Request $request): RedirectResponse
    {
        $status = $this->sendResetLinkSafely([
            'email' => $request->user()->email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return redirect()->route('profile.edit')->with('status', 'password-reset-link-sent');
        }

        $message = $status === Password::RESET_THROTTLED
            ? __('Pautan telah diminta baru-baru ini. Sila tunggu sebelum mencuba lagi.')
            : __('Pautan tidak dapat dihantar. Sila pastikan SMTP dikonfigurasi dan cuba lagi.');

        return redirect()->route('profile.edit')->withErrors([
            'email' => $message,
        ], 'passwordReset');
    }

    /**
     * @param  array{email: string}  $credentials
     */
    private function sendResetLinkSafely(array $credentials): ?string
    {
        if (! $this->passwordResetMailCanBeDelivered()) {
            return null;
        }

        try {
            return Password::sendResetLink($credentials);
        } catch (Throwable) {
            // Do not log exceptions here: mail payloads may contain the reset token.
            return null;
        }
    }

    /**
     * Prevent reset URLs and tokens from being written to application logs.
     */
    private function passwordResetMailCanBeDelivered(): bool
    {
        $mailer = (string) config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport", $mailer);

        if (app()->environment('testing') && $transport === 'array') {
            return true;
        }

        return $this->mailerAvoidsLogging($mailer);
    }

    /**
     * Reject direct or fallback log/array transports outside automated tests.
     *
     * @param  array<string>  $visited
     */
    private function mailerAvoidsLogging(string $mailer, array $visited = []): bool
    {
        if ($mailer === '' || in_array($mailer, $visited, true)) {
            return false;
        }

        $visited[] = $mailer;
        $configuration = config("mail.mailers.{$mailer}");

        if (! is_array($configuration)) {
            return false;
        }

        $transport = $configuration['transport'] ?? $mailer;

        if (in_array($transport, ['log', 'array'], true)) {
            return false;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            foreach ($configuration['mailers'] ?? [] as $nestedMailer) {
                if (! $this->mailerAvoidsLogging((string) $nestedMailer, $visited)) {
                    return false;
                }
            }
        }

        return true;
    }
}
