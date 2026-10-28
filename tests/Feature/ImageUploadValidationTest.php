<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_transparent_rgba_png_2048_by_930_is_accepted_for_stamp_and_signature(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ajk = User::factory()->create(['role' => 'ajk']);
        $stamp = $this->transparentPng('cop-rasmi.png', 2048, 930);
        $this->assertGreaterThan(450 * 1024, $stamp->getSize());
        $this->assertLessThan(550 * 1024, $stamp->getSize());

        $this->actingAs($admin)->put(route('admin.organization.stamp'), [
            'official_stamp' => $stamp,
        ])->assertSessionHas('success')->assertSessionDoesntHaveErrors();

        $stampPath = Setting::query()->where('key', 'organization_official_stamp_path')->value('value');
        Storage::disk('local')->assertExists($stampPath);

        $this->actingAs($ajk)->put(route('profile.signature.update'), [
            'digital_signature' => $this->transparentPng('tandatangan.png', 2048, 930),
        ])->assertSessionHas('status', 'signature-updated')->assertSessionDoesntHaveErrors();

        Storage::disk('local')->assertExists($ajk->fresh()->digital_signature_path);
    }

    public function test_png_and_jpeg_within_limits_are_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ajk = User::factory()->create(['role' => 'ajk']);

        $this->actingAs($admin)->put(route('admin.organization.stamp'), [
            'official_stamp' => UploadedFile::fake()->image('cop.jpg', 800, 400),
        ])->assertSessionHas('success')->assertSessionDoesntHaveErrors();

        $this->actingAs($ajk)->put(route('profile.signature.update'), [
            'digital_signature' => UploadedFile::fake()->image('tandatangan.png', 600, 200),
        ])->assertSessionHas('status', 'signature-updated')->assertSessionDoesntHaveErrors();
    }

    public function test_image_smaller_than_100_pixels_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ajk = User::factory()->create(['role' => 'ajk']);
        $message = 'Dimensi cop rasmi Warga Cakna mestilah antara 100 × 100 dan 3000 × 3000 piksel.';

        $this->actingAs($admin)->put(route('admin.organization.stamp'), [
            'official_stamp' => UploadedFile::fake()->image('terlalu-kecil.png', 99, 100),
        ])->assertSessionHasErrors(['official_stamp' => $message]);

        $this->actingAs($ajk)->put(route('profile.signature.update'), [
            'digital_signature' => UploadedFile::fake()->image('terlalu-kecil.jpg', 100, 99),
        ])->assertSessionHasErrors([
            'digital_signature' => 'Dimensi tandatangan digital mestilah antara 100 × 100 dan 3000 × 3000 piksel.',
        ]);
    }

    public function test_image_larger_than_3000_pixels_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ajk = User::factory()->create(['role' => 'ajk']);

        $this->actingAs($admin)->put(route('admin.organization.stamp'), [
            'official_stamp' => UploadedFile::fake()->image('terlalu-besar.png', 3001, 100),
        ])->assertSessionHasErrors('official_stamp');

        $this->actingAs($ajk)->put(route('profile.signature.update'), [
            'digital_signature' => UploadedFile::fake()->image('terlalu-besar.jpg', 100, 3001),
        ])->assertSessionHasErrors('digital_signature');
    }

    public function test_image_larger_than_two_megabytes_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ajk = User::factory()->create(['role' => 'ajk']);

        $this->actingAs($admin)->put(route('admin.organization.stamp'), [
            'official_stamp' => UploadedFile::fake()->image('cop.png', 300, 300)->size(2049),
        ])->assertSessionHasErrors([
            'official_stamp' => 'Saiz fail cop rasmi mestilah tidak melebihi 2 MB.',
        ]);

        $this->actingAs($ajk)->put(route('profile.signature.update'), [
            'digital_signature' => UploadedFile::fake()->image('tandatangan.jpg', 300, 300)->size(2049),
        ])->assertSessionHasErrors([
            'digital_signature' => 'Saiz fail tandatangan digital mestilah tidak melebihi 2 MB.',
        ]);
    }

    public function test_non_image_renamed_as_png_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ajk = User::factory()->create(['role' => 'ajk']);
        $notImage = '<?php echo "not an image"; ?>';
        $fakeStamp = $this->nonImageUpload('cop.png', $notImage);
        $fakeStampPath = $fakeStamp->getPathname();

        $this->actingAs($admin)->put(route('admin.organization.stamp'), [
            'official_stamp' => $fakeStamp,
        ])->assertSessionHasErrors('official_stamp');
        @unlink($fakeStampPath);

        $fakeSignature = $this->nonImageUpload('tandatangan.png', $notImage);
        $fakeSignaturePath = $fakeSignature->getPathname();
        $this->actingAs($ajk)->put(route('profile.signature.update'), [
            'digital_signature' => $fakeSignature,
        ])->assertSessionHasErrors('digital_signature');
        @unlink($fakeSignaturePath);

        $this->assertDatabaseMissing('settings', ['key' => 'organization_official_stamp_path']);
        $this->assertNull($ajk->fresh()->digital_signature_path);
    }

    private function transparentPng(string $name, int $width, int $height): TestingFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);
        $ink = imagecolorallocatealpha($image, 0, 90, 50, 20);
        imagerectangle($image, 10, 10, $width - 11, $height - 11, $ink);
        mt_srand(1337);
        for ($i = 0; $i < 75000; $i++) {
            $pixel = imagecolorallocatealpha(
                $image,
                mt_rand(0, 255),
                mt_rand(0, 255),
                mt_rand(0, 255),
                mt_rand(20, 110),
            );
            imagesetpixel($image, mt_rand(0, $width - 1), mt_rand(0, $height - 1), $pixel);
        }

        ob_start();
        imagepng($image, null, 6);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function nonImageUpload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'invalid-image-');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, UPLOAD_ERR_OK, true);
    }
}
