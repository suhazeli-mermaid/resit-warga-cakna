<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Rules\SecureImageDimensions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function updateSignature(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasVoucherRole(), 403);
        $request->validate([
            'digital_signature' => [
                'required',
                File::image()
                    ->types(['png', 'jpg', 'jpeg'])
                    ->extensions(['png', 'jpg', 'jpeg'])
                    ->max(2048),
                new SecureImageDimensions,
            ],
        ], [
            'digital_signature.required' => 'Sila pilih fail tandatangan digital.',
            'digital_signature.file' => 'Fail tandatangan digital mestilah fail imej yang sah.',
            'digital_signature.image' => 'Tandatangan digital mestilah imej PNG, JPG atau JPEG yang sah.',
            'digital_signature.mimes' => 'Tandatangan digital hanya menerima format PNG, JPG atau JPEG.',
            'digital_signature.extensions' => 'Tandatangan digital hanya menerima sambungan fail .png, .jpg atau .jpeg.',
            'digital_signature.max' => 'Saiz fail tandatangan digital mestilah tidak melebihi 2 MB.',
            'digital_signature.uploaded' => 'Fail tandatangan digital gagal dimuat naik. Sila cuba semula.',
        ], [
            'digital_signature' => 'tandatangan digital',
        ]);

        $path = $request->file('digital_signature')->store('users/'.$request->user()->id.'/signatures', 'local');
        if (! $path) {
            return Redirect::route('profile.edit')->withErrors([
                'digital_signature' => 'Tandatangan tidak dapat disimpan. Sila cuba semula.',
            ]);
        }
        $oldPath = $request->user()->digital_signature_path;
        $request->user()->forceFill([
            'digital_signature_path' => $path,
            'digital_signature_checksum' => hash('sha256', Storage::disk('local')->get($path)),
            'digital_signature_uploaded_at' => now(),
        ])->save();

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        return Redirect::route('profile.edit')->with('status', 'signature-updated');
    }

    public function signature(Request $request)
    {
        abort_unless($request->user()?->hasVoucherRole(), 403);
        abort_unless($request->user()->hasDigitalSignature(), 404);
        $path = $request->user()->digital_signature_path;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return Storage::disk('local')->response(
            $path,
            'tandatangan-digital.'.($extension ?: 'png'),
            ['Cache-Control' => 'private, no-store, max-age=0'],
        );
    }

    public function destroySignature(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasVoucherRole(), 403);
        $path = $request->user()->digital_signature_path;
        $request->user()->forceFill([
            'digital_signature_path' => null,
            'digital_signature_checksum' => null,
            'digital_signature_uploaded_at' => null,
        ])->save();
        if ($path) {
            Storage::disk('local')->delete($path);
        }

        return Redirect::route('profile.edit')->with('status', 'signature-deleted');
    }
}
