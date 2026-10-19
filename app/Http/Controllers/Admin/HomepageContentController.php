<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\HomepageContent;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomepageContentController extends Controller
{
    /**
     * Memaparkan borang pengurusan muka depan & tetapan tandatangan.
     */
    public function edit(Request $request)
    {
        $this->authorizeContentManager($request);
        $campaigns = Campaign::orderBy('created_at', 'desc')->get();
        $hero = HomepageContent::firstOrNew(['section_key' => 'hero']);

        return view('admin.homepage.edit', compact('campaigns', 'hero'));
    }

    /**
     * Kemaskini kandungan hero muka depan tanpa nilai demo hard-coded.
     */
    public function updateHero(Request $request)
    {
        $this->authorizeContentManager($request);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:1000',
            'cta_text' => 'nullable|string|max:80',
            'cta_link' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! Str::startsWith((string) $value, ['/', '#', 'https://', 'http://'])) {
                        $fail('Pautan CTA mesti pautan relatif, fragmen halaman, HTTP atau HTTPS.');
                    }
                },
            ],
            'is_published' => 'required|boolean',
            'banner_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:3072',
        ]);

        $hero = HomepageContent::firstOrNew(['section_key' => 'hero']);

        if ($request->hasFile('banner_image')) {
            $validated['banner_image'] = $request->file('banner_image')->store('homepage', 'public');
        }

        $hero->fill($validated);
        $hero->section_key = 'hero';
        $hero->save();

        return redirect()->back()->with('success', 'Hero muka depan berjaya dikemas kini.');
    }

    /**
     * Kemaskini maklumat kempen (Tajuk, Caption, Poster, Sasaran RM).
     */
    public function updateCampaign(Request $request, $id)
    {
        $this->authorizeContentManager($request);
        $campaign = Campaign::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'target_amount' => 'required|numeric|min:1',
            'is_active' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:3072',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $disk = Storage::disk('public');

            // Kekalkan nama asal, tetapi bersihkan aksara
            // yang tidak sesuai untuk nama fail.
            $originalName = pathinfo(
                $file->getClientOriginalName(),
                PATHINFO_FILENAME
            );

            $name = Str::of($originalName)
                ->replaceMatches('/[^\pL\pN._ -]+/u', '-')
                ->trim(' .-_')
                ->limit(100, '');

            $name = (string) $name;

            if ($name === '') {
                $name = 'gambar-kempen';
            }

            // Sambungan berdasarkan jenis fail yang disahkan,
            // bukan semata-mata sambungan asal pengguna.
            $extension = strtolower($file->extension());

            $filename = $name.'.'.$extension;
            $counter = 2;

            // Elakkan menimpa fail yang sudah wujud.
            while ($disk->exists('campaigns/'.$filename)) {
                $filename = $name.'-'.$counter.'.'.$extension;
                $counter++;
            }

            // Simpan gambar baharu dahulu.
            $newPath = $file->storeAs(
                'campaigns',
                $filename,
                'public'
            );

            if (! $newPath) {
                throw new \RuntimeException(
                    'Gagal menyimpan gambar kempen.'
                );
            }

            $validated['image_path'] = $newPath;
        }

        $campaign->update($validated);

        return redirect()->back()->with('success', "Kempen '{$campaign->title}' berjaya dikemaskini!");
    }

    /**
     * Muat naik tandatangan digital Bendahari.
     */
    public function uploadSignature(Request $request)
    {
        $this->authorizeContentManager($request);
        $request->validate([
            'signature' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        $existing = Setting::where('key', 'treasurer_signature')->first();

        if ($existing && $existing->value && Storage::disk('public')->exists($existing->value)) {
            Storage::disk('public')->delete($existing->value);
        }

        $path = $request->file('signature')->store('signatures', 'public');

        Setting::updateOrCreate(
            ['key' => 'treasurer_signature'],
            ['value' => $path]
        );

        return redirect()->back()->with('success', 'Tandatangan digital Bendahari berjaya dikemaskini!');
    }

    private function authorizeContentManager(Request $request): void
    {
        abort_unless($request->user()?->isAjk(), 403, 'Akses pengurusan kandungan diperlukan.');
    }
}
