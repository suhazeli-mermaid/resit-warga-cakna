<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignDetail;
use App\Models\CampaignUpdate;
use App\Models\CampaignMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CampaignCmsController extends Controller
{
    private function authorizeAdmin(): void
    {
        $role = strtolower(
            trim((string) (auth()->user()?->role ?? ''))
        );

        abort_unless(
            auth()->check() &&
            in_array($role, ['admin', 'super_admin'], true),
            403,
            'Akses pentadbiran diperlukan.'
        );
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'kempen';
        $slug = $base;
        $number = 2;

        while (Campaign::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $number++;
        }

        return $slug;
    }

    private function saveImage($file, string $directory): string
    {
        $disk = Storage::disk('public');

        $name = pathinfo(
            $file->getClientOriginalName(),
            PATHINFO_FILENAME
        );

        $name = (string) Str::of($name)
            ->replaceMatches('/[^\pL\pN._ -]+/u', '-')
            ->trim(' .-_')
            ->limit(100, '');

        if ($name === '') {
            $name = 'gambar-program';
        }

        $extension = strtolower($file->extension());
        $filename = $name . '.' . $extension;
        $counter = 2;

        while ($disk->exists($directory . '/' . $filename)) {
            $filename = $name . '-' . $counter++ . '.' . $extension;
        }

        $path = $file->storeAs(
            $directory,
            $filename,
            'public'
        );

        if (!$path) {
            throw new \RuntimeException('Gagal menyimpan gambar.');
        }

        return $path;
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.campaigns.create');
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'target_amount' => 'required|numeric|min:1',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        $imagePath = null;

        try {
            if ($request->hasFile('image')) {
                $imagePath = $this->saveImage(
                    $request->file('image'),
                    'campaigns'
                );
            }

            $campaign = DB::transaction(function () use ($data, $imagePath) {
                return Campaign::create([
                    'title' => $data['title'],
                    'slug' => $this->uniqueSlug($data['title']),
                    'description' => $data['description'],
                    'target_amount' => $data['target_amount'],
                    'collected_amount' => 0,
                    'image_path' => $imagePath,
                    'is_active' => false,
                ]);
            });
        } catch (\Throwable $e) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $e;
        }

        return redirect()
            ->route('admin.cms.edit', $campaign->id)
            ->with('success', 'Kempen baharu berjaya disimpan sebagai tidak aktif.');
    }

    public function edit(int $id)
    {
        $this->authorizeAdmin();

        $campaign = Campaign::findOrFail($id);

        $detail = CampaignDetail::where(
            'campaign_id',
            $campaign->id
        )->first();

        $updates = CampaignUpdate::where(
            'campaign_id',
            $campaign->id
        )
            ->with('media')
            ->orderByDesc('activity_date')
            ->orderByDesc('id')
            ->get();

        $media = CampaignMedia::where(
            'campaign_id',
            $campaign->id
        )
            ->orderBy('sort_order')
            ->get();

        return view(
            'admin.campaigns.edit',
            compact('campaign', 'detail', 'updates', 'media')
        );
    }

    public function saveDetails(Request $request, int $id)
    {
        $this->authorizeAdmin();

        $campaign = Campaign::findOrFail($id);

        $data = $request->validate([
            'background' => 'nullable|string',
            'objectives' => 'nullable|string',
            'beneficiaries' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'organiser' => 'nullable|string|max:255',
            'contact_info' => 'nullable|string|max:255',
            'is_published' => 'required|boolean',
        ]);

        CampaignDetail::updateOrCreate(
            ['campaign_id' => $campaign->id],
            $data
        );

        return back()->with(
            'success',
            'Maklumat lengkap kempen berjaya disimpan.'
        );
    }

    public function storeUpdate(Request $request, int $id)
    {
        $this->authorizeAdmin();

        $campaign = Campaign::findOrFail($id);

        $data = $request->validate([
            'activity_date' => 'required|date',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'is_published' => 'required|boolean',
        ]);

        $campaignUpdate = CampaignUpdate::create([
            'campaign_id' => $campaign->id,
            ...$data,
        ]);

        return back()->with(
            'success',
            'Aktiviti timeline berjaya ditambah.'
        );
    }

    public function updateActivity(Request $request, int $id)
    {
        $this->authorizeAdmin();

        $update = CampaignUpdate::findOrFail($id);

        $data = $request->validate([
            'activity_date' => 'required|date',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'is_published' => 'required|boolean',
        ]);

        $update->update($data);

        return back()->with(
            'success',
            'Aktiviti berjaya dikemas kini.'
        );
    }

    public function storeMedia(Request $request, int $id)
    {
        $this->authorizeAdmin();

        $campaign = Campaign::findOrFail($id);

        $data = $request->validate([
            'campaign_update_id' => [
                'nullable',
                Rule::exists('campaign_updates', 'id')
                    ->where('campaign_id', $campaign->id),
            ],
            'media_type' => 'required|in:image,youtube',
            'images' => 'required_if:media_type,image|array|max:20',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:3072',
            'youtube_url' => 'required_if:media_type,youtube|nullable|url|max:255',
            'caption' => 'nullable|string|max:255',
            'is_published' => 'required|boolean',
        ]);

        $updateId = $data['campaign_update_id'] ?? null;

        if ($data['media_type'] === 'youtube') {
            $url = $data['youtube_url'];

            $host = strtolower(
                parse_url($url, PHP_URL_HOST) ?: ''
            );

            if (!in_array($host, [
                'youtube.com',
                'www.youtube.com',
                'm.youtube.com',
                'youtu.be',
                'www.youtu.be',
                'youtube-nocookie.com',
                'www.youtube-nocookie.com',
            ], true)) {
                throw ValidationException::withMessages([
                    'youtube_url' => 'Hanya pautan YouTube dibenarkan.',
                ]);
            }

            CampaignMedia::create([
                'campaign_id' => $campaign->id,
                'campaign_update_id' => $updateId,
                'media_type' => 'youtube',
                'youtube_url' => $url,
                'caption' => $data['caption'] ?? null,
                'is_published' => $data['is_published'],
            ]);
        } else {
            $paths = [];

            try {
                foreach ($request->file('images', []) as $file) {
                    $path = $this->saveImage(
                        $file,
                        'campaign-gallery/' . $campaign->id
                    );

                    $paths[] = $path;
                }

                DB::transaction(function () use (
                    $paths,
                    $campaign,
                    $updateId,
                    $data
                ) {
                    foreach ($paths as $path) {
                        CampaignMedia::create([
                            'campaign_id' => $campaign->id,
                            'campaign_update_id' => $updateId,
                            'media_type' => 'image',
                            'file_path' => $path,
                            'caption' => $data['caption'] ?? null,
                            'is_published' => $data['is_published'],
                        ]);
                    }
                });
            } catch (\Throwable $e) {
                foreach ($paths as $path) {
                    Storage::disk('public')->delete($path);
                }

                throw $e;
            }
        }

        return back()->with(
            'success',
            'Media kempen berjaya ditambah.'
        );
    }

    public function deleteMedia(int $id)
    {
        $this->authorizeAdmin();

        $media = CampaignMedia::findOrFail($id);

        // Padam rekod dahulu. Kekalkan fail fizikal
        // supaya tiada kehilangan gambar jika masih dirujuk.
        $media->delete();

        return back()->with(
            'success',
            'Media dikeluarkan daripada galeri.'
        );
    }
}
