<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMedia;
use App\Models\Setting;
use App\Services\OrganizationBrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationBrandingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_public_logo_is_reused_without_copying_or_modifying_it(): void
    {
        $path = public_path('images/logo-warga-cakna.png');
        $before = [
            'checksum' => hash_file('sha256', $path),
            'size' => filesize($path),
            'modified_at' => filemtime($path),
        ];

        $branding = app(OrganizationBrandingService::class)->documentBranding();

        $this->assertSame('public_fallback', $branding['logo_source']);
        $this->assertNull($branding['logo_media_id']);
        $this->assertSame($path, $branding['logo_local_path']);
        $this->assertSame('/images/logo-warga-cakna.png', $branding['logo_browser_url']);
        $this->assertStringStartsWith('data:image/png;base64,', $branding['logo_data_uri']);
        $this->assertSame($before['checksum'], $branding['logo_checksum']);
        $this->assertSame($before, [
            'checksum' => hash_file('sha256', $path),
            'size' => filesize($path),
            'modified_at' => filemtime($path),
        ]);
    }

    public function test_configured_gallery_media_id_takes_priority_and_uses_the_same_bytes(): void
    {
        Storage::fake('public');
        $campaign = Campaign::query()->create([
            'title' => 'Identiti Organisasi',
            'slug' => 'identiti-organisasi',
        ]);
        $contents = $this->png();
        $path = 'campaign-gallery/'.$campaign->id.'/logo-rasmi.png';
        Storage::disk('public')->put($path, $contents);
        $media = CampaignMedia::query()->create([
            'campaign_id' => $campaign->id,
            'media_type' => 'image',
            'file_path' => $path,
            'caption' => 'Logo Rasmi Warga Cakna',
            'is_published' => true,
        ]);
        Setting::query()->create([
            'key' => 'organization_logo_media_id',
            'value' => (string) $media->id,
        ]);

        $branding = app(OrganizationBrandingService::class)->documentBranding();

        $this->assertSame('campaign_media', $branding['logo_source']);
        $this->assertSame($media->id, $branding['logo_media_id']);
        $this->assertSame('/storage/'.$path, $branding['logo_browser_url']);
        $this->assertSame(Storage::disk('public')->path($path), $branding['logo_local_path']);
        $this->assertSame(hash('sha256', $contents), $branding['logo_checksum']);
        $this->assertSame($contents, base64_decode(substr($branding['logo_data_uri'], strlen('data:image/png;base64,'))));
        Storage::disk('public')->assertExists($path);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_browser_logo_path_respects_the_apache_alias_base_path(): void
    {
        $request = Request::create(
            'http://localhost/resit-wargacakna/admin/kewangan/baucar',
            'GET',
            server: [
                'SCRIPT_NAME' => '/resit-wargacakna/index.php',
                'SCRIPT_FILENAME' => public_path('index.php'),
            ],
        );
        $this->app->instance('request', $request);

        $branding = app(OrganizationBrandingService::class)->documentBranding();

        $this->assertSame('/resit-wargacakna/images/logo-warga-cakna.png', $branding['logo_browser_url']);
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
