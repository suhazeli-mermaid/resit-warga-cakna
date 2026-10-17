<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HomepageContentController extends Controller
{
    public function edit()
    {
        $hero = HomepageContent::firstOrCreate(['section_key' => 'hero'], [
            'title' => 'Bantu Golongan Terpinggir Bersama WARGA CAKNA',
            'subtitle' => 'Sumbangan anda membawa sinar harapan dan meringankan beban mereka yang memerlukan.',
            'cta_text' => 'Sumbang Sekarang',
            'cta_link' => '#campaigns',
            'is_published' => true
        ]);

        $about = HomepageContent::firstOrCreate(['section_key' => 'about'], [
            'title' => 'Mengenai Persatuan Prihatin Warga Terpinggir Terengganu',
            'body' => 'WARGA CAKNA komited menjalankan aktiviti kebajikan dan kemanusiaan untuk membantu golongan kurang bernasib baik di Terengganu dan luar negara.',
            'is_published' => true
        ]);

        return view('admin.homepage.edit', compact('hero', 'about'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'hero_title' => 'required|string|max:255',
            'hero_subtitle' => 'nullable|string|max:255',
            'hero_cta_text' => 'nullable|string|max:100',
            'hero_cta_link' => 'nullable|string|max:255',
            'hero_banner' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'about_title' => 'required|string|max:255',
            'about_body' => 'required|string',
            'hero_published' => 'nullable|boolean',
            'about_published' => 'nullable|boolean',
        ]);

        // Update Hero Section
        $hero = HomepageContent::where('section_key', 'hero')->first();
        $heroData = [
            'title' => $validated['hero_title'],
            'subtitle' => $validated['hero_subtitle'],
            'cta_text' => $validated['hero_cta_text'],
            'cta_link' => $validated['hero_cta_link'],
            'is_published' => $request->has('hero_published'),
        ];

        if ($request->hasFile('hero_banner')) {
            if ($hero->banner_image) {
                Storage::disk('public')->delete($hero->banner_image);
            }
            $heroData['banner_image'] = $request->file('hero_banner')->store('homepage', 'public');
        }

        $hero->update($heroData);

        // Update About Section
        $about = HomepageContent::where('section_key', 'about')->first();
        $about->update([
            'title' => $validated['about_title'],
            'body' => $validated['about_body'],
            'is_published' => $request->has('about_published'),
        ]);

        return redirect()->back()->with('success', 'Kandungan Muka Depan berjaya dikemaskini dan diterbitkan.');
    }
}