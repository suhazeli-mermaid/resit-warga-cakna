<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $resetUserId = null;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, &$resetUserId) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                $resetUserId = $user->getAuthIdentifier();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $this->deleteDatabaseSessionsFor($resetUserId);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', __('Kata laluan berjaya ditukar. Sila log masuk semula.'));
        }

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }

    /**
     * Invalidate all stored browser sessions after a successful password reset.
     */
    private function deleteDatabaseSessionsFor(int|string|null $userId): void
    {
        if ($userId === null || config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');
        $connection = DB::connection(config('session.connection'));

        if ($connection->getSchemaBuilder()->hasColumn($table, 'user_id')) {
            $connection->table($table)->where('user_id', $userId)->delete();
        }
    }
}
