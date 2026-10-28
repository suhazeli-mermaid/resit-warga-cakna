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
        try {
            return Password::sendResetLink($credentials);
        } catch (Throwable) {
            // Do not log exceptions here: mail payloads may contain the reset token.
            return null;
        }
    }
}
