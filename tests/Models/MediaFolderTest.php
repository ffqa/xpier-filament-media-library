<?php

namespace Xpier\FilamentMediaLibrary\Tests\Models;

use Xpier\FilamentMediaLibrary\Models\MediaFolder;
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;
use Xpier\FilamentMediaLibrary\Tests\TestCase;

class MediaFolderTest extends TestCase
{
    public function test_storage_path_of_root_folder_is_code(): void
    {
        $folder = MediaFolder::query()->create([
            'code' => 'pets',
            'name' => '宠物',
            'sort' => 0,
            'is_active' => true,
        ]);

        $this->assertSame('pets', $folder->storage_path);
    }

    public function test_storage_path_of_child_folder_nests_parent_code(): void
    {
        $parent = MediaFolder::query()->create([
            'code' => 'pets',
            'name' => '宠物',
            'sort' => 0,
        ]);
        $child = MediaFolder::query()->create([
            'parent_id' => $parent->id,
            'code' => 'dogs',
            'name' => '狗狗',
            'sort' => 1,
        ]);

        $this->assertSame('pets/dogs', $child->storage_path);
    }

    public function test_code_is_normalized_and_defaulted_from_name(): void
    {
        $folder = MediaFolder::query()->create([
            'name' => 'My Folder!',
            'sort' => 0,
        ]);

        $this->assertSame('my-folder', $folder->code);
    }

    public function test_chinese_name_falls_back_to_hashed_code(): void
    {
        $folder = MediaFolder::query()->create([
            'name' => '我的目录',
            'sort' => 0,
        ]);

        // Chinese characters are stripped by the ASCII code sanitizer, so the
        // code falls back to a name-based hash to stay unique.
        $this->assertSame('folder-'.substr(md5('我的目录'), 0, 8), $folder->code);
    }

    public function test_rejects_deeply_nested_subfolders(): void
    {
        $parent = MediaFolder::query()->create([
            'code' => 'pets',
            'name' => '宠物',
            'sort' => 0,
        ]);
        $child = MediaFolder::query()->create([
            'parent_id' => $parent->id,
            'code' => 'dogs',
            'name' => '狗狗',
            'sort' => 0,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        MediaFolder::query()->create([
            'parent_id' => $child->id,
            'code' => 'puppies',
            'name' => '幼犬',
            'sort' => 0,
        ]);
    }

    public function test_options_indent_child_folders(): void
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
            'sort' => 1,
        ]);

        $this->assertSame([
            'pets' => '宠物',
            'pets/dogs' => '　└ 狗狗',
        ], MediaFolder::options());
    }

    public function test_find_by_storage_path(): void
    {
        $parent = MediaFolder::query()->create([
            'code' => 'pets',
            'name' => '宠物',
            'sort' => 0,
        ]);
        $child = MediaFolder::query()->create([
            'parent_id' => $parent->id,
            'code' => 'dogs',
            'name' => '狗狗',
            'sort' => 1,
        ]);

        $this->assertTrue(MediaFolder::findByStoragePath('pets/dogs')->is($child));
        $this->assertNull(MediaFolder::findByStoragePath('missing'));
    }

    public function test_media_files_query_scopes_to_storage_path(): void
    {
        $folder = MediaFolder::query()->create([
            'code' => 'pets',
            'name' => '宠物',
            'sort' => 0,
        ]);

        MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/pets/1.png',
            'type' => MediaLibrary::TYPE_IMAGE,
            'source' => MediaLibrary::SOURCE_ADMIN,
            'folder' => 'pets',
        ]);
        MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/other/1.png',
            'type' => MediaLibrary::TYPE_IMAGE,
            'source' => MediaLibrary::SOURCE_ADMIN,
            'folder' => 'other',
        ]);

        $this->assertSame(1, $folder->mediaFilesQuery()->count());
    }

    public function test_root_folders_exclude_inactive(): void
    {
        MediaFolder::query()->create(['code' => 'a', 'name' => 'A', 'sort' => 0, 'is_active' => true]);
        MediaFolder::query()->create(['code' => 'b', 'name' => 'B', 'sort' => 1, 'is_active' => false]);

        $this->assertCount(1, MediaFolder::rootFolders());
        $this->assertCount(2, MediaFolder::rootFolders(activeOnly: false));
    }

    public function test_resolve_storage_path_excludes_inactive_folders_by_default(): void
    {
        MediaFolder::query()->create(['code' => 'hidden', 'name' => '隐藏', 'sort' => 0, 'is_active' => false]);

        $this->assertNull(MediaFolder::resolveStoragePath('hidden'));
        $this->assertSame('hidden', MediaFolder::resolveStoragePath('hidden', activeOnly: false));
    }

    public function test_find_by_storage_path_can_filter_inactive(): void
    {
        $folder = MediaFolder::query()->create(['code' => 'hidden', 'name' => '隐藏', 'sort' => 0, 'is_active' => false]);

        $this->assertNull(MediaFolder::findByStoragePath('hidden', activeOnly: true));
        $this->assertTrue(MediaFolder::findByStoragePath('hidden')->is($folder));
    }
}
