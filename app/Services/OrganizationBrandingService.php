<?php

namespace App\Services;

use App\Models\CampaignMedia;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class OrganizationBrandingService
{
    private const LOGO_MEDIA_SETTING = 'organization_logo_media_id';

    private const FALLBACK_PUBLIC_PATH = 'images/logo-warga-cakna.png';

    private ?array $resolved = null;

    public function documentBranding(): array
    {
        return $this->resolved ??= $this->resolve();
    }

    private function resolve(): array
    {
        $media = $this->configuredMedia();
        if ($media) {
            $contents = Storage::disk('public')->get($media->file_path);
            $image = $this->imageData($contents);

            if ($image) {
                return [
                    'logo_browser_url' => $this->browserPath('storage/'.$media->file_path),
                    'logo_data_uri' => $image['data_uri'],
                    'logo_local_path' => Storage::disk('public')->path($media->file_path),
                    'logo_source' => 'campaign_media',
                    'logo_media_id' => $media->id,
                    'logo_checksum' => hash('sha256', $contents),
                ];
            }
        }

        $relativePath = self::FALLBACK_PUBLIC_PATH;
        $localPath = public_path($relativePath);
        if (is_file($localPath) && is_readable($localPath)) {
            $contents = file_get_contents($localPath);
            $image = is_string($contents) ? $this->imageData($contents) : null;

            if ($image) {
                return [
                    'logo_browser_url' => $this->browserPath($relativePath),
                    'logo_data_uri' => $image['data_uri'],
                    'logo_local_path' => $localPath,
                    'logo_source' => 'public_fallback',
                    'logo_media_id' => null,
                    'logo_checksum' => hash('sha256', $contents),
                ];
            }
        }

        return [
            'logo_browser_url' => null,
            'logo_data_uri' => null,
            'logo_local_path' => null,
            'logo_source' => 'text_only',
            'logo_media_id' => null,
            'logo_checksum' => null,
        ];
    }

    private function configuredMedia(): ?CampaignMedia
    {
        $mediaId = Setting::query()->where('key', self::LOGO_MEDIA_SETTING)->value('value');
        if (! is_string($mediaId) || ! ctype_digit($mediaId)) {
            return null;
        }

        $media = CampaignMedia::query()
            ->whereKey((int) $mediaId)
            ->where('media_type', 'image')
            ->whereNotNull('file_path')
            ->first();

        return $media && Storage::disk('public')->exists($media->file_path) ? $media : null;
    }

    private function imageData(string $contents): ?array
    {
        $image = @getimagesizefromstring($contents);
        $mime = is_array($image) ? ($image['mime'] ?? null) : null;

        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        return ['data_uri' => 'data:'.$mime.';base64,'.base64_encode($contents)];
    }

    private function browserPath(string $relativePath): string
    {
        $baseUrl = rtrim(request()->getBaseUrl(), '/');

        return $baseUrl.'/'.ltrim($relativePath, '/');
    }
}
