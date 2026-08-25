<?php

namespace Xpier\FilamentMediaLibrary\Tests\Components;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use ReflectionProperty;
use Xpier\FilamentMediaLibrary\Components\MediaPicker;
use Xpier\FilamentMediaLibrary\Models\MediaFolder;
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;
use Xpier\FilamentMediaLibrary\Tests\TestCase;

class MediaPickerTest extends TestCase
{
    public function test_defaults(): void
    {
        $picker = new MediaPicker('cover_image');

        $this->assertFalse($picker->isMultiple());
        $this->assertNull($picker->getRelationshipName());
        $this->assertSame('public', $picker->getDisk());
        $this->assertSame('public', $picker->getVisibility());
    }

    public function test_multiple_flag(): void
    {
        $picker = new MediaPicker('gallery');
        $picker->multiple();

        $this->assertTrue($picker->isMultiple());
    }

    public function test_disk_and_visibility_overrides(): void
    {
        $picker = new MediaPicker('cover_image');
        $picker->disk('private_s3')->visibility('private');

        $this->assertSame('private_s3', $picker->getDisk());
        $this->assertSame('private', $picker->getVisibility());
    }

    public function test_disk_and_visibility_fall_back_to_config(): void
    {
        config()->set('filament-media-library.disk', 'r2');
        config()->set('filament-media-library.visibility', 'private');

        $picker = new MediaPicker('cover_image');

        $this->assertSame('r2', $picker->getDisk());
        $this->assertSame('private', $picker->getVisibility());
    }

    public function test_relationship_registers_save_and_load_hooks(): void
    {
        $picker = new MediaPicker('gallery');
        $picker->multiple()->relationship('galleryMedia')->orderColumn('order');

        $this->assertSame('galleryMedia', $picker->getRelationshipName());

        $saveHook = (new ReflectionProperty($picker, 'saveRelationshipsUsing'))->getValue($picker);
        $loadHook = (new ReflectionProperty($picker, 'loadStateFromRelationshipsUsing'))->getValue($picker);

        $this->assertNotNull($saveHook);
        $this->assertNotNull($loadHook);
        $this->assertFalse($picker->isDehydrated());
    }

    public function test_plain_picker_is_dehydrated(): void
    {
        $picker = new MediaPicker('cover_image');

        $this->assertTrue($picker->isDehydrated());
    }

    public function test_get_selected_media_resolves_single_id(): void
    {
        $media = MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/one.png',
            'original_name' => 'one.png',
            'alt_text' => '第一张',
            'type' => MediaLibrary::TYPE_IMAGE,
            'source' => MediaLibrary::SOURCE_ADMIN,
        ]);

        [$livewire, $picker] = $this->mountPicker();
        $livewire->data = ['media_id' => (string) $media->id];

        $selected = $picker->getSelectedMedia();

        $this->assertNotNull($selected);
        $this->assertSame($media->id, $selected['id']);
        $this->assertSame('one.png', $selected['name']);
        $this->assertSame('第一张', $selected['note']);
    }

    public function test_get_selected_media_resolves_multiple_ids_in_order(): void
    {
        $first = MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/a.png',
            'original_name' => 'a.png',
            'type' => MediaLibrary::TYPE_IMAGE,
            'source' => MediaLibrary::SOURCE_ADMIN,
        ]);
        $second = MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/b.png',
            'original_name' => 'b.png',
            'type' => MediaLibrary::TYPE_IMAGE,
            'source' => MediaLibrary::SOURCE_ADMIN,
        ]);

        [$livewire, $picker] = $this->mountPicker(multiple: true);
        $livewire->data = ['media_id' => [(string) $second->id, (string) $first->id]];

        $selected = $picker->getSelectedMedia();

        $this->assertNotNull($selected);
        $this->assertCount(2, $selected);
        $this->assertSame([$second->id, $first->id], array_column($selected, 'id'));
    }

    public function test_get_selected_media_returns_null_when_empty(): void
    {
        [, $picker] = $this->mountPicker();
        $this->assertNull($picker->getSelectedMedia());

        [, $multi] = $this->mountPicker(multiple: true);
        $this->assertNull($multi->getSelectedMedia());
    }

    public function test_get_selected_media_treats_string_state_as_url(): void
    {
        [$livewire, $picker] = $this->mountPicker();
        $livewire->data = ['media_id' => 'https://cdn.example.com/legacy/old.png'];

        $selected = $picker->getSelectedMedia();

        $this->assertNotNull($selected);
        $this->assertNull($selected['id']);
        $this->assertSame('https://cdn.example.com/legacy/old.png', $selected['url']);
        $this->assertSame('old.png', $selected['name']);
    }

    public function test_get_selected_media_resolves_multiple_urls_preview_only(): void
    {
        [$livewire, $picker] = $this->mountPicker(multiple: true);
        $picker->storeMode('url');
        $livewire->data = [
            'media_id' => [
                'https://cdn.example.com/a.png',
                'https://cdn.example.com/b.png',
            ],
        ];

        $selected = $picker->getSelectedMedia();

        $this->assertNotNull($selected);
        $this->assertCount(2, $selected);
        $this->assertNull($selected[0]['id']);
        $this->assertSame('https://cdn.example.com/a.png', $selected[0]['url']);
        $this->assertSame('b.png', $selected[1]['name']);
    }

    public function test_disabled_folder_falls_back_to_root_in_browser(): void
    {
        MediaFolder::query()->create(['code' => 'hidden', 'name' => '隐藏', 'sort' => 0, 'is_active' => false]);

        [$livewire, $picker] = $this->mountPicker();
        $livewire->data = ['picker_folder' => 'hidden'];

        $browser = $picker->getBrowserState('hidden');

        $this->assertSame(MediaPicker::FOLDER_ROOT, $browser['current']);
    }

    /**
     * @return array{0: SchemaHost, 1: MediaPicker}
     */
    protected function mountPicker(bool $multiple = false): array
    {
        $livewire = new SchemaHost;
        $schema = Schema::make($livewire)->statePath('data');

        $picker = new MediaPicker('media_id');
        if ($multiple) {
            $picker->multiple();
        }
        $picker->container($schema);

        return [$livewire, $picker];
    }
}

class SchemaHost extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public array $data = [];
}
