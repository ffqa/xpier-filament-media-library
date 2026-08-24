<?php

namespace Xpier\FilamentMediaLibrary\Tests\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
}
