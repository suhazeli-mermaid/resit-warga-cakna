<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Rules\SecureImageDimensions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class OrganizationSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $stampPath = Setting::query()->where('key', 'organization_official_stamp_path')->value('value');

        return view('admin.organization.edit', [
            'hasStamp' => filled($stampPath) && Storage::disk('local')->exists($stampPath),
        ]);
    }

    public function updateStamp(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $request->validate([
            'official_stamp' => [
                'required',
                File::image()
                    ->types(['png', 'jpg', 'jpeg'])
                    ->extensions(['png', 'jpg', 'jpeg'])
                    ->max(2048),
                new SecureImageDimensions,
            ],
        ], [
            'official_stamp.required' => 'Sila pilih fail cop rasmi Warga Cakna.',
            'official_stamp.file' => 'Fail cop rasmi mestilah fail imej yang sah.',
            'official_stamp.image' => 'Cop rasmi mestilah imej PNG, JPG atau JPEG yang sah.',
            'official_stamp.mimes' => 'Cop rasmi hanya menerima format PNG, JPG atau JPEG.',
            'official_stamp.extensions' => 'Cop rasmi hanya menerima sambungan fail .png, .jpg atau .jpeg.',
            'official_stamp.max' => 'Saiz fail cop rasmi mestilah tidak melebihi 2 MB.',
            'official_stamp.uploaded' => 'Fail cop rasmi gagal dimuat naik. Sila cuba semula.',
        ], [
            'official_stamp' => 'cop rasmi Warga Cakna',
        ]);

        $path = $request->file('official_stamp')->store('organization/stamps', 'local');
        if (! $path) {
            return back()->withErrors(['official_stamp' => 'Cop rasmi tidak dapat disimpan. Sila cuba semula.']);
        }
        $oldPath = Setting::query()->where('key', 'organization_official_stamp_path')->value('value');

        Setting::query()->updateOrCreate(
            ['key' => 'organization_official_stamp_path'],
            ['value' => $path],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'organization_official_stamp_checksum'],
            ['value' => hash('sha256', Storage::disk('local')->get($path))],
        );
        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('success', 'Cop rasmi organisasi berjaya disimpan secara peribadi. Dokumen lama kekal menggunakan snapshot asal.');
    }

    public function stamp(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $path = Setting::query()->where('key', 'organization_official_stamp_path')->value('value');
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return Storage::disk('local')->response(
            $path,
            'cop-rasmi-warga-cakna.'.($extension ?: 'png'),
            ['Cache-Control' => 'private, no-store, max-age=0'],
        );
    }
}
