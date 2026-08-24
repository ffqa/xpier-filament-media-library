<?php

namespace Xpier\FilamentMediaLibrary\Tests\Models;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Xpier\FilamentMediaLibrary\Events\MediaDeleted;
use Xpier\FilamentMediaLibrary\Events\MediaRestored;
use Xpier\FilamentMediaLibrary\Events\MediaUploaded;
use Xpier\FilamentMediaLibrary\Models\MediaFolder;
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;
use Xpier\FilamentMediaLibrary\Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    public function test_type_options_are_translated(): void
    {
        $options = MediaLibrary::typeOptions();

        $this->assertSame('图片', $options[MediaLibrary::TYPE_IMAGE]);
        $this->assertSame('文件', $options[MediaLibrary::TYPE_FILE]);
        $this->assertSame('视频', $options[MediaLibrary::TYPE_VIDEO]);
    }

    public function test_admin_folder_options_fall_back_to_translated_presets(): void
    {
        $this->assertSame([], MediaFolder::query()->get()->all());

        $options = MediaLibrary::adminFolderOptions();

        $this->assertSame('通用', $options['general']);
        $this->assertSame('宠物', $options['pets']);
    }

    public function test_admin_folder_options_use_database_folders_when_available(): void
    {
        MediaFolder::query()->create([
            'code' => 'custom',
            'name' => '自定义目录',
            'sort' => 0,
            'is_active' => true,
        ]);

        $options = MediaLibrary::adminFolderOptions();

        $this->assertSame(['custom' => '自定义目录'], $options);
    }

    public function test_url_attribute_resolves_through_storage(): void
    {
        Storage::fake('public');

        $media = MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/library/general/image/2026/08/test.png',
            'original_name' => 'test.png',
            'type' => MediaLibrary::TYPE_IMAGE,
            'source' => MediaLibrary::SOURCE_ADMIN,
        ]);

        $this->assertSame(
            Storage::disk('public')->url('media/library/general/image/2026/08/test.png'),
            $media->url,
        );
    }

    public function test_url_attribute_uses_public_url_resolver(): void
    {
        config()->set('filament-media-library.public_urls', ['public' => 'https://cdn.example.com']);

        $media = MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/test.png',
            'type' => MediaLibrary::TYPE_IMAGE,
            'source' => MediaLibrary::SOURCE_ADMIN,
        ]);

        $this->assertSame('https://cdn.example.com/media/test.png', $media->url);
    }

    public function test_soft_delete_removes_physical_file_by_default(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/soft-delete.png', 'content');

        $media = MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/soft-delete.png',
            'type' => MediaLibrary::TYPE_IMAGE,
            'source' => MediaLibrary::SOURCE_ADMIN,
        ]);

        $media->delete();

        $this->assertSoftDeleted('media_library', ['id' => $media->id]);
        $this->assertFalse(Storage::disk('public')->exists('media/soft-delete.png'));
        $this->assertNull(MediaLibrary::query()->find($media->id));
        $this->assertNotNull(MediaLibrary::withTrashed()->find($media->id));
    }

    public function test_soft_delete_keeps_physical_file_when_disabled(): void
    {
        config()->set('filament-media-library.delete_file_on_delete', false);

        Storage::fake('public');
        Storage::disk('public')->put('media/soft-delete-keep.png', 'content');

        $media = MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/soft-delete-keep.png',
            'type' => MediaLibrary::TYPE_IMAGE,
            'source' => MediaLibrary::SOURCE_ADMIN,
        ]);

        $media->delete();

        $this->assertSoftDeleted('media_library', ['id' => $media->id]);
        $this->assertTrue(Storage::disk('public')->exists('media/soft-delete-keep.png'));
    }

    public function test_physical_delete_mode_removes_record_and_file_immediately(): void
    {
        config()->set('filament-media-library.delete_mode', 'physical');

        Storage::fake('public');
        Storage::disk('public')->put('media/physical.png', 'content');

        $media = MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/physical.png',
            'type' => MediaLibrary::TYPE_IMAGE,
            'source' => MediaLibrary::SOURCE_ADMIN,
        ]);

        $result = $media->delete();

        $this->assertTrue((bool) $result);
        $this->assertNull(MediaLibrary::withTrashed()->find($media->id));
        $this->assertFalse(Storage::disk('public')->exists('media/physical.png'));
    }

    public function test_force_delete_removes_physical_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/force-delete.png', 'content');

        $media = MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/force-delete.png',
            'type' => MediaLibrary::TYPE_IMAGE,
            'source' => MediaLibrary::SOURCE_ADMIN,
        ]);

        $media->forceDelete();

        $this->assertFalse(Storage::disk('public')->exists('media/force-delete.png'));
        $this->assertNull(MediaLibrary::withTrashed()->find($media->id));
    }

    public function test_dispatches_media_events_on_lifecycle(): void
    {
        Event::fake([MediaUploaded::class, MediaDeleted::class, MediaRestored::class]);

        $media = MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/events.png',
            'type' => MediaLibrary::TYPE_IMAGE,
            'source' => MediaLibrary::SOURCE_ADMIN,
        ]);

        Event::assertDispatched(MediaUploaded::class, fn (MediaUploaded $event): bool => $event->media->is($media));

        $media->delete();
        Event::assertDispatched(MediaDeleted::class, fn (MediaDeleted $event): bool => $event->media->is($media) && $event->force === false);

        $media->restore();
        Event::assertDispatched(MediaRestored::class, fn (MediaRestored $event): bool => $event->media->is($media));
    }

    public function test_folder_label_uses_translated_fallback(): void
    {
        $media = new MediaLibrary(['folder' => null]);

        $this->assertSame('未分类', $media->folderLabel());
        $this->assertSame('general', (new MediaLibrary(['folder' => 'general']))->folderLabel());
    }
}
