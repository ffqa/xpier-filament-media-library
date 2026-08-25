<?php

namespace Xpier\FilamentMediaLibrary\Tests\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Xpier\FilamentMediaLibrary\Models\MediaFolder;
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;
use Xpier\FilamentMediaLibrary\Services\AdminMediaService;
use Xpier\FilamentMediaLibrary\Tests\TestCase;

class AdminMediaServiceTest extends TestCase
{
    public function test_store_upload_persists_media_record(): void
    {
        Storage::fake('public');

        $media = app(AdminMediaService::class)->storeUpload(
            file: UploadedFile::fake()->image('cover.png'),
            folder: 'general',
            type: MediaLibrary::TYPE_IMAGE,
        );

        $this->assertInstanceOf(MediaLibrary::class, $media);
        $this->assertSame('public', $media->disk);
        $this->assertSame(MediaLibrary::SOURCE_ADMIN, $media->source);
        $this->assertSame('general', $media->folder);
        $this->assertSame('cover.png', $media->original_name);
        $this->assertSame('image', $media->type);
        $this->assertTrue(Storage::disk('public')->exists($media->path));
        $this->assertStringStartsWith('media/library/general/image/', $media->path);
    }

    public function test_store_upload_honors_custom_disk_and_visibility(): void
    {
        Storage::fake('private_s3');
        config()->set('filament-media-library.visibility', 'public');

        $media = app(AdminMediaService::class)->storeUpload(
            file: UploadedFile::fake()->image('doc.png'),
            folder: 'pets',
            type: MediaLibrary::TYPE_IMAGE,
            disk: 'private_s3',
            visibility: 'private',
        );

        $this->assertSame('private_s3', $media->disk);
        $this->assertStringStartsWith('media/library/pets/image/', $media->path);
    }

    public function test_normalize_folder_uses_database_folder_path(): void
    {
        $parent = MediaFolder::query()->create([
            'code' => 'pets',
            'name' => '宠物',
            'sort' => 0,
        ]);
        MediaFolder::query()->create([
            'parent_id' => $parent->id,
            'code' => 'dogs',
            'name' => '狗狗',
            'sort' => 0,
        ]);

        Storage::fake('public');

        $media = app(AdminMediaService::class)->storeUpload(
            file: UploadedFile::fake()->image('x.png'),
            folder: 'pets/dogs',
            type: MediaLibrary::TYPE_IMAGE,
        );

        $this->assertSame('pets/dogs', $media->folder);
        $this->assertStringStartsWith('media/library/pets/dogs/image/', $media->path);
    }

    public function test_normalize_folder_falls_back_to_default_module_for_unknown_folder(): void
    {
        Storage::fake('public');

        $media = app(AdminMediaService::class)->storeUpload(
            file: UploadedFile::fake()->image('x.png'),
            folder: 'non-existent-folder',
            type: MediaLibrary::TYPE_IMAGE,
        );

        $this->assertSame('general', $media->folder);
    }

    public function test_store_upload_preserves_original_filename_metadata(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('report.pdf', 1, 'application/pdf');

        $media = app(AdminMediaService::class)->storeUpload(
            file: $file,
            folder: 'general',
            type: MediaLibrary::TYPE_FILE,
        );

        $this->assertSame('report.pdf', $media->original_name);
        $this->assertSame('pdf', $media->extension);
        $this->assertSame('application/pdf', $media->mime_type);
        $this->assertSame(1024, $media->size);
    }

    public function test_store_upload_uses_detected_extension_not_client_name(): void
    {
        Storage::fake('public');

        // A real PNG payload with a misleading ".php" filename must be stored
        // with the MIME-detected extension, never the client-provided one.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $tmp = tempnam(sys_get_temp_dir(), 'ml');
        file_put_contents($tmp, $png);

        $file = new UploadedFile($tmp, 'evil.php', 'image/png', null, true);

        $media = app(AdminMediaService::class)->storeUpload(
            file: $file,
            folder: 'general',
            type: MediaLibrary::TYPE_IMAGE,
        );

        $this->assertStringEndsWith('.png', $media->path);
        $this->assertSame('png', $media->extension);
        @unlink($tmp);
    }

    public function test_store_upload_rejects_disallowed_extension(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;');

        try {
            app(AdminMediaService::class)->storeUpload(
                file: $file,
                folder: 'general',
                type: MediaLibrary::TYPE_FILE,
            );

            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('upload', $e->errors());
            $this->assertStringContainsString('php', $e->errors()['upload'][0]);
        }
    }

    public function test_store_upload_rejects_oversized_file(): void
    {
        config()->set('filament-media-library.max_size', 0.001); // ~1 KB

        Storage::fake('public');

        $file = UploadedFile::fake()->create('big.png', 10); // 10 KB

        try {
            app(AdminMediaService::class)->storeUpload(
                file: $file,
                folder: 'general',
                type: MediaLibrary::TYPE_IMAGE,
            );

            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('upload', $e->errors());
            $this->assertStringContainsString('MB', $e->errors()['upload'][0]);
        }
    }
}
