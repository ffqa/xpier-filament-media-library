<?php

namespace Xpier\FilamentMediaLibrary\Tests\Resources;

use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaFolderResource;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaLibraryResource;
use Xpier\FilamentMediaLibrary\Models\MediaFolder;
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;
use Xpier\FilamentMediaLibrary\Tests\TestCase;

class ResourceTableTest extends TestCase
{
    public function test_media_library_columns_survive_null_record(): void
    {
        $table = $this->buildTable(MediaLibraryResource::class);

        foreach ($table->getColumns() as $column) {
            $name = $column->getName();
            $this->assertColumnSurvivesNullRecord($column, $name);
        }

        $this->assertTrue(true);
    }

    public function test_media_library_columns_render_with_real_record(): void
    {
        $media = MediaLibrary::query()->create([
            'disk' => 'public',
            'path' => 'media/library/general/image/2026/08/test.png',
            'original_name' => 'test.png',
            'type' => MediaLibrary::TYPE_IMAGE,
            'size' => 2048,
            'folder' => 'general',
            'source' => MediaLibrary::SOURCE_ADMIN,
        ]);

        $table = $this->buildTable(MediaLibraryResource::class);

        foreach ($table->getColumns() as $column) {
            $column->record($media);
            $this->assertColumnEvaluatesSafely($column);
        }

        $this->assertTrue(true);
    }

    public function test_media_folder_columns_survive_null_record(): void
    {
        $table = $this->buildTable(MediaFolderResource::class);

        foreach ($table->getColumns() as $column) {
            $name = $column->getName();
            $this->assertColumnSurvivesNullRecord($column, $name);
        }

        $this->assertTrue(true);
    }

    public function test_media_folder_columns_render_with_real_record(): void
    {
        $folder = MediaFolder::query()->create([
            'code' => 'general',
            'name' => '通用',
            'sort' => 0,
            'is_active' => true,
        ]);

        $table = $this->buildTable(MediaFolderResource::class);

        foreach ($table->getColumns() as $column) {
            $column->record($folder);
            $this->assertColumnEvaluatesSafely($column);
        }

        $this->assertTrue(true);
    }

    protected function assertColumnSurvivesNullRecord(object $column, string $name): void
    {
        try {
            $column->isVisible();
        } catch (\Throwable $e) {
            $this->fail("visible() throws on '{$name}' with null record: {$e->getMessage()}");
        }

        try {
            $column->getState();
        } catch (\Throwable $e) {
            $this->fail("getState() throws on '{$name}' with null record: {$e->getMessage()}");
        }

        if (method_exists($column, 'formatState')) {
            try {
                $column->formatState(null);
            } catch (\Throwable $e) {
                $this->fail("formatState(null) throws on '{$name}' with null record: {$e->getMessage()}");
            }
        }
    }

    protected function assertColumnEvaluatesSafely(object $column): void
    {
        $name = $column->getName();

        try {
            $column->isVisible();
        } catch (\Throwable $e) {
            $this->fail("visible() failed on '{$name}' with real record: {$e->getMessage()}");
        }

        $state = null;
        try {
            $state = $column->getState();
        } catch (\Throwable $e) {
            $this->fail("getState() failed on '{$name}' with real record: {$e->getMessage()}");
        }

        if (method_exists($column, 'formatState')) {
            try {
                $column->formatState($state);
            } catch (\Throwable $e) {
                $this->fail("formatState() failed on '{$name}' with real record: {$e->getMessage()}");
            }
        }
    }

    protected function buildTable(string $resource): Table
    {
        $host = new TableHost;
        $table = Table::make($host);

        // InteractsWithTable::$table is typed and only initialised inside
        // bootedInteractsWithTable(), which we never call. Bind it manually so
        // closures that resolve the livewire/table dependency do not crash on
        // an uninitialized typed property.
        $ref = new \ReflectionProperty($host, 'table');
        $ref->setValue($host, $table);

        return $resource::table($table);
    }
}

class TableHost extends Component implements HasTable
{
    use InteractsWithTable;

    public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
    {
        return null;
    }
}
