<?php

namespace Xpier\FilamentMediaLibrary\Components;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Xpier\FilamentMediaLibrary\Filament\Resources\MediaFolderResource;
use Xpier\FilamentMediaLibrary\Models\MediaFolder;
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;
use Xpier\FilamentMediaLibrary\Services\AdminMediaService;
use Xpier\FilamentMediaLibrary\Support\ThumbnailProvider;

/**
 * Unified platform media picker: folder browser + upload in one modal.
 *
 * Usage in any Filament Resource form:
 *   MediaPicker::make('cover_image')->label('封面图')->module('articles')->storeMode('url')
 */
class MediaPicker extends Field
{
    public const FOLDER_ALL = '__all__';

    public const FOLDER_ROOT = '__root__';

    public const FOLDER_SEARCH = '__search__';

    protected string $view = 'filament-media-library::media-library.media-picker';

    protected string | Closure | null $mediaType = MediaLibrary::TYPE_IMAGE;

    /** Default platform folder code (articles / pets / …). */
    protected string | Closure $module = 'general';

    /** 'id' stores media_library.id; 'url' stores the resolved URL string (backward-compatible with URL/path columns). */
    protected string $storeMode = 'id';

    protected ?Closure $modifyMediaQueryUsing = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (MediaPicker $component): Action => $component->getPickerAction(),
        ]);
    }

    public function mediaType(string | Closure | null $type): static
    {
        $this->mediaType = $type;

        return $this;
    }

    public function module(string | Closure $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function storeMode(string $mode): static
    {
        $this->storeMode = $mode;

        return $this;
    }

    public function modifyMediaQueryUsing(?Closure $callback): static
    {
        $this->modifyMediaQueryUsing = $callback;

        return $this;
    }

    public function getMediaType(): ?string
    {
        return $this->evaluate($this->mediaType);
    }

    public function getStoreMode(): string
    {
        return $this->storeMode;
    }

    public function getModule(): string
    {
        return $this->evaluate($this->module);
    }

    public function getPickerActionName(): string
    {
        return 'openMediaPicker';
    }

    /**
     * Browser entries for the current location (folders + media) or search results.
     *
     * @return array{
     *     current: string,
     *     breadcrumbs: list<array{key: string, label: string}>,
     *     can_upload: bool,
     *     upload_folder: string,
     *     entries: list<array<string, mixed>>,
     *     is_search: bool,
     * }
     */
    public function getBrowserState(?string $folder = null, ?string $search = null): array
    {
        $search = trim((string) $search);

        if ($search !== '') {
            $results = $this->baseMediaQuery()
                ->where(function ($query) use ($search): void {
                    $query->where('original_name', 'like', "%{$search}%")
                        ->orWhere('alt_text', 'like', "%{$search}%");
                })
                ->latest('id')
                ->limit(100)
                ->get();

            return [
                'current' => self::FOLDER_SEARCH,
                'breadcrumbs' => [
                    ['key' => self::FOLDER_ROOT, 'label' => __('filament-media-library::media-library.picker.root')],
                    ['key' => self::FOLDER_SEARCH, 'label' => __('filament-media-library::media-library.picker.search_results', ['keyword' => $search])],
                ],
                'can_upload' => false,
                'upload_folder' => 'general',
                'entries' => array_map(
                    fn (array $item): array => $item + ['kind' => 'media'],
                    $this->mapMediaFiles($results)
                ),
                'is_search' => true,
            ];
        }

        $current = $this->normalizeBrowserFolder($folder);
        $breadcrumbs = $this->breadcrumbsFor($current);
        $entries = [];

        if ($current === self::FOLDER_ALL) {
            $entries = array_map(
                fn (array $item): array => $item + ['kind' => 'media'],
                $this->mapMediaFiles(
                    $this->baseMediaQuery()->latest('id')->limit(50)->get()
                )
            );

            return [
                'current' => $current,
                'breadcrumbs' => $breadcrumbs,
                'can_upload' => false,
                'upload_folder' => 'general',
                'entries' => $entries,
                'is_search' => false,
            ];
        }

        if ($current === self::FOLDER_ROOT) {
            foreach (MediaFolder::rootFolders() as $root) {
                $entries[] = $this->folderEntry($root);
            }

            $known = MediaFolder::knownStoragePaths();
            $uncategorized = $this->baseMediaQuery()
                ->where(function ($query) use ($known): void {
                    $query->whereNull('folder')
                        ->orWhere('folder', '')
                        ->orWhereNotIn('folder', $known);
                })
                ->latest('id')
                ->limit(50)
                ->get();

            foreach ($this->mapMediaFiles($uncategorized) as $item) {
                $entries[] = $item + ['kind' => 'media'];
            }

            return [
                'current' => $current,
                'breadcrumbs' => $breadcrumbs,
                'can_upload' => true,
                'upload_folder' => 'general',
                'entries' => $entries,
                'is_search' => false,
            ];
        }

        $folderModel = MediaFolder::findByStoragePath($current);
        if ($folderModel instanceof MediaFolder && $folderModel->parent_id !== null) {
            $parent = $folderModel->relationLoaded('parent')
                ? $folderModel->parent
                : $folderModel->parent()->first();
            $parentKey = $parent instanceof MediaFolder ? $parent->storage_path : self::FOLDER_ROOT;
            $entries[] = [
                'kind' => 'up',
                'key' => $parentKey,
                'label' => __('filament-media-library::media-library.picker.up'),
            ];
        } else {
            $entries[] = [
                'kind' => 'up',
                'key' => self::FOLDER_ROOT,
                'label' => __('filament-media-library::media-library.picker.up'),
            ];
        }

        if ($folderModel instanceof MediaFolder) {
            foreach ($folderModel->children()->where('is_active', true)->get() as $child) {
                $entries[] = $this->folderEntry($child);
            }
        }

        foreach ($this->mapMediaFiles($this->mediaQueryForFolder($current)->limit(50)->get()) as $item) {
            $entries[] = $item + ['kind' => 'media'];
        }

        return [
            'current' => $current,
            'breadcrumbs' => $breadcrumbs,
            'can_upload' => true,
            'upload_folder' => $current,
            'entries' => $entries,
            'is_search' => false,
        ];
    }

    public function getSelectedMedia(): ?array
    {
        $state = $this->getState();

        if (blank($state)) {
            return null;
        }

        $thumbnail = app(ThumbnailProvider::class);

        // Numeric state: resolve by media_library.id
        if (is_numeric($state)) {
            $media = MediaLibrary::query()->find((int) $state);

            if (! $media instanceof MediaLibrary || blank($media->url)) {
                return null;
            }

            $url = (string) $media->url;

            return [
                'id' => $media->getKey(),
                'url' => $url,
                'thumb' => $thumbnail->thumbnail($url, 480) ?: $url,
                'name' => $media->original_name ?: basename($media->path),
                'note' => (string) ($media->alt_text ?? ''),
            ];
        }

        // String state: treat as URL directly (backward-compatible with old path/URL data)
        $url = (string) $state;

        return [
            'id' => null,
            'url' => $url,
            'thumb' => $thumbnail->thumbnail($url, 480) ?: $url,
            'name' => basename(parse_url($url, PHP_URL_PATH) ?: $url),
            'note' => '',
        ];
    }

    public function getPickerAction(): Action
    {
        return Action::make($this->getPickerActionName())
            ->label(__('filament-media-library::media-library.picker.pick_from_library'))
            ->icon(Heroicon::OutlinedPhoto)
            ->color('gray')
            ->modalHeading(__('filament-media-library::media-library.picker.modal_heading'))
            ->modalDescription(__('filament-media-library::media-library.picker.modal_description'))
            ->modalSubmitActionLabel(__('filament-media-library::media-library.picker.confirm'))
            ->modalCancelActionLabel(__('filament-media-library::media-library.picker.cancel'))
            ->modalWidth('7xl')
            ->extraModalWindowAttributes(['class' => 'fi-media-picker-modal'])
            ->fillForm(fn (): array => [
                'picker_folder' => $this->normalizeBrowserFolder($this->getModule()),
                'search_query' => '',
                'selected_media_id' => $this->getState() !== null ? (string) $this->getState() : null,
            ])
            ->schema([
                Hidden::make('picker_folder')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (Set $set): void {
                        $set('selected_media_id', null);
                        $set('upload_new', null);
                        $set('search_query', '');
                    }),
                Hidden::make('search_query')
                    ->live()
                    ->dehydrated(false),
                FileUpload::make('upload_new')
                    ->hiddenLabel()
                    ->acceptedFileTypes(fn (): array => match ($this->getMediaType()) {
                        MediaLibrary::TYPE_VIDEO => ['video/mp4', 'video/webm', 'video/quicktime'],
                        null => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'video/quicktime'],
                        default => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                    })
                    ->maxFiles(1)
                    ->previewable(false)
                    ->imageEditor()
                    ->imagePreviewHeight('0')
                    ->panelLayout('integrated')
                    ->extraAttributes(['class' => 'fi-media-picker-file-upload fi-media-picker-file-upload-slim'])
                    ->extraFieldWrapperAttributes(['class' => 'fi-media-picker-upload-hidden'])
                    ->disk(MediaLibrary::defaultDisk())
                    ->directory(fn (Get $get): string => trim((string) config('filament-media-library.directory', 'media'), '/')
                        .'/library/'.$this->uploadFolderFromState($get('picker_folder')).'/'.($this->getMediaType() ?? 'file'))
                    ->visibility('public')
                    ->storeFiles(false)
                    ->dehydrated(false)
                    ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                        if ($state === null || $state === []) {
                            return;
                        }

                        $file = is_array($state) ? ($state[0] ?? null) : $state;
                        if (! $file instanceof TemporaryUploadedFile && ! $file instanceof UploadedFile) {
                            return;
                        }

                        $uploadFolder = $this->uploadFolderFromState($get('picker_folder'));
                        if ($uploadFolder === self::FOLDER_ALL || $uploadFolder === self::FOLDER_ROOT) {
                            $uploadFolder = 'general';
                        }

                        $media = app(AdminMediaService::class)->storeUpload(
                            file: $file,
                            folder: $uploadFolder,
                            type: $this->getMediaType(),
                            userId: auth()->id(),
                        );

                        $set('selected_media_id', (string) $media->getKey());
                        $set('upload_new', null);
                    }),
                ViewField::make('selected_media_id')
                    ->hiddenLabel()
                    ->required()
                    ->live()
                    ->view('filament-media-library::media-library.media-picker-grid')
                    ->viewData(function (Get $get, ViewField $component): array {
                        $search = (string) ($get('search_query') ?? '');
                        $browser = $this->getBrowserState(
                            (string) ($get('picker_folder') ?: self::FOLDER_ROOT),
                            $search !== '' ? $search : null,
                        );
                        $mediaStatePath = $component->getStatePath();
                        $base = preg_replace('/\.selected_media_id$/', '', (string) $mediaStatePath) ?: '';
                        $folderStatePath = ($base !== '' ? $base.'.' : '').'picker_folder';
                        $searchStatePath = ($base !== '' ? $base.'.' : '').'search_query';

                        return [
                            'browser' => $browser,
                            'folderStatePath' => $folderStatePath,
                            'searchStatePath' => $searchStatePath,
                            'foldersUrl' => MediaFolderResource::getUrl('index'),
                        ];
                    }),
            ])
            ->action(function (array $data): void {
                $mediaId = $data['selected_media_id'] ?? null;

                if (blank($mediaId)) {
                    $this->state(null);

                    return;
                }

                if ($this->storeMode === 'url') {
                    $media = MediaLibrary::query()->find((int) $mediaId);
                    $this->state($media instanceof MediaLibrary ? (string) $media->url : null);

                    return;
                }

                $this->state((int) $mediaId);
            });
    }

    protected function normalizeBrowserFolder(?string $folder): string
    {
        $folder = trim((string) $folder);
        if ($folder === '' || $folder === self::FOLDER_ROOT) {
            return self::FOLDER_ROOT;
        }
        if ($folder === self::FOLDER_ALL) {
            return self::FOLDER_ALL;
        }

        return MediaFolder::resolveStoragePath($folder) ?? $folder;
    }

    protected function uploadFolderFromState(mixed $folder): string
    {
        $normalized = $this->normalizeBrowserFolder(is_string($folder) ? $folder : null);
        if ($normalized === self::FOLDER_ALL || $normalized === self::FOLDER_ROOT) {
            return 'general';
        }

        return $normalized;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    protected function breadcrumbsFor(string $current): array
    {
        $crumbs = [
            ['key' => self::FOLDER_ALL, 'label' => __('filament-media-library::media-library.picker.all')],
            ['key' => self::FOLDER_ROOT, 'label' => __('filament-media-library::media-library.picker.root')],
        ];

        if ($current === self::FOLDER_ALL || $current === self::FOLDER_ROOT) {
            return $crumbs;
        }

        $model = MediaFolder::findByStoragePath($current);
        if (! $model instanceof MediaFolder) {
            $crumbs[] = ['key' => $current, 'label' => $current];

            return $crumbs;
        }

        if ($model->parent_id !== null) {
            $parent = $model->relationLoaded('parent') ? $model->parent : $model->parent()->first();
            if ($parent instanceof MediaFolder) {
                $crumbs[] = ['key' => $parent->storage_path, 'label' => $parent->name];
            }
        }

        $crumbs[] = ['key' => $model->storage_path, 'label' => $model->name];

        return $crumbs;
    }

    /**
     * @return array{kind: string, key: string, label: string, count: int}
     */
    protected function folderEntry(MediaFolder $folder): array
    {
        $path = $folder->storage_path;
        $childCount = $folder->children()->where('is_active', true)->count();
        $query = MediaLibrary::query()
            ->library()
            ->where('folder', $path);
        $mediaType = $this->getMediaType();
        if ($mediaType !== null) {
            $query->where('type', $mediaType);
        }
        $fileCount = $query->count();

        return [
            'kind' => 'folder',
            'key' => $path,
            'label' => $folder->name,
            'count' => $fileCount + $childCount,
        ];
    }

    protected function baseMediaQuery()
    {
        $query = MediaLibrary::query()
            ->library();

        $mediaType = $this->getMediaType();
        if ($mediaType !== null) {
            $query->where('type', $mediaType);
        }

        if ($this->modifyMediaQueryUsing) {
            $this->evaluate($this->modifyMediaQueryUsing, ['query' => $query]);
        }

        return $query;
    }

    protected function mediaQueryForFolder(string $folder)
    {
        return $this->baseMediaQuery()
            ->where('folder', $folder)
            ->latest('id');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MediaLibrary>|iterable<MediaLibrary>  $files
     * @return list<array{id: int|string, url: string, thumb: string, name: string, note: string}>
     */
    protected function mapMediaFiles(iterable $files): array
    {
        $thumbnail = app(ThumbnailProvider::class);
        $items = [];
        foreach ($files as $media) {
            if (! $media instanceof MediaLibrary) {
                continue;
            }
            $url = (string) $media->url;
            $items[] = [
                'id' => $media->getKey(),
                'url' => $url,
                'thumb' => $thumbnail->thumbnail($url, 320) ?: $url,
                'name' => $media->original_name ?: basename($media->path),
                'note' => (string) ($media->alt_text ?? ''),
            ];
        }

        return $items;
    }
}
