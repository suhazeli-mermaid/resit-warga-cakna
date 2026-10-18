<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomepageContentController extends Controller
{
    /**
     * Memaparkan borang pengurusan muka depan & tetapan tandatangan.
     */
    public function edit()
    {
        $campaigns = Campaign::orderBy('created_at', 'desc')->get();
        return view('admin.homepage.edit', compact('campaigns'));
    }

    /**
     * Kemaskini maklumat kempen (Tajuk, Caption, Poster, Sasaran RM).
     */
    public function updateCampaign(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);

        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'required|string',
            'target_amount' => 'required|numeric|min:1',
            'is_active'     => 'required|boolean',
            'image'         => 'nullable|image|mimes:jpeg,jpg,png,webp|max:3072',
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

    $filename = $name . '.' . $extension;
    $counter = 2;

    // Elakkan menimpa fail yang sudah wujud.
    while ($disk->exists('campaigns/' . $filename)) {
        $filename = $name . '-' . $counter . '.' . $extension;
        $counter++;
    }

    // Simpan gambar baharu dahulu.
    $newPath = $file->storeAs(
        'campaigns',
        $filename,
        'public'
    );

    if (!$newPath) {
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
}