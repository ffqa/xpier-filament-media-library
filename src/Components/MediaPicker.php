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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
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

    protected string $view = 'filament-media-library::media-picker';

    protected string | Closure | null $mediaType = MediaLibrary::TYPE_IMAGE;

    /** Default platform folder code (articles / pets / …). */
    protected string | Closure $module = 'general';

    /** 'id' stores media_library.id; 'url' stores the resolved URL string (backward-compatible with URL/path columns). */
    protected string $storeMode = 'id';

    /** Filesystem disk for uploads; falls back to the package config. */
    protected string | Closure | null $disk = null;

    /** File visibility for uploads ('public' | 'private'); falls back to the package config. */
    protected string | Closure | null $visibility = null;

    protected bool | Closure $isMultiple = false;

    /** Eloquent relationship name used to load/save the selected media (e.g. 'featuredImage', 'gallery'). */
    protected ?string $relationshipName = null;

    /** Key column on the related MediaLibrary model used for load/save. */
    protected string $relationshipKey = 'id';

    /** Pivot column to persist ordering for BelongsToMany relationships. */
    protected ?string $orderColumn = null;

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

    public function disk(string | Closure | null $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function getDisk(): string
    {
        return (string) ($this->evaluate($this->disk) ?: MediaLibrary::defaultDisk());
    }

    public function visibility(string | Closure | null $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getVisibility(): string
    {
        return (string) ($this->evaluate($this->visibility) ?: config('filament-media-library.visibility', 'public'));
    }

    public function multiple(bool | Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    /**
     * Bind the field to an Eloquent relationship so the selection is loaded from and
     * saved to the related media instead of a plain column.
     *
     * Single (default): BelongsTo — the related media key is associated to the record.
     * Multiple: BelongsToMany — related media are synced on save.
     */
    public function relationship(string $name, string $key = 'id'): static
    {
        $this->relationshipName = $name;
        $this->relationshipKey = $key;

        // The selection lives on the relationship, not on a model column.
        $this->dehydrated(false);

        $this->saveRelationshipsUsing(function (MediaPicker $component, Model $record, $state): void {
            $relationship = $record->{$component->relationshipName}();

            if ($relationship instanceof BelongsTo) {
                $relationship->associate(blank($state) ? null : $state);
                $record->save();

                return;
            }

            if ($relationship instanceof BelongsToMany) {
                $ids = array_values(array_filter(
                    Arr::wrap($state),
                    fn ($id): bool => filled($id) && is_numeric($id),
                ));

                if ($component->orderColumn !== null) {
                    $relationship->sync(
                        collect($ids)->mapWithKeys(fn ($id, $index): array => [
                            $id => [$component->orderColumn => $index],
                        ])->all()
                    );
                } else {
                    $relationship->sync($ids);
                }
            }
        });

        $this->loadStateFromRelationshipsUsing(function (MediaPicker $component, $state): void {
            if (filled($state)) {
                return;
            }

            $record = $component->getRecord();
            if (! $record instanceof Model) {
                return;
            }

            $relationship = $record->{$component->relationshipName}();

                if ($relationship instanceof BelongsToMany) {
                    $query = $relationship->getQuery();

                    if ($component->orderColumn !== null) {
                        $query->orderBy($relationship->qualifyPivotColumn($component->orderColumn));
                    }

                    $component->state(
                        $query->pluck($component->relationshipKey)
                            ->map(fn ($key): string => (string) $key)
                            ->all()
                    );

                    return;
                }

            if ($relationship instanceof BelongsTo) {
                $related = $relationship->getResults();
                $component->state($related?->getAttribute($component->relationshipKey));
            }
        });

        return $this;
    }

    public function orderColumn(string $column): static
    {
        $this->orderColumn = $column;

        return $this;
    }

    public function getRelationshipName(): ?string
    {
        return $this->relationshipName;
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

    /**
     * @return list<array{id: int|string|null, url: string, thumb: string, name: string, note: string}>|array{id: int|string|null, url: string, thumb: string, name: string, note: string}|null
     */
    public function getSelectedMedia(): ?array
    {
        $state = $this->getState();
        $thumbnail = app(ThumbnailProvider::class);

        if ($this->isMultiple()) {
            $items = [];
            $numericIds = [];

            foreach (Arr::wrap($state) as $value) {
                if (filled($value) && is_numeric($value)) {
                    $numericIds[] = (int) $value;

                    continue;
                }

                // storeMode('url'): the state holds URL strings. The preview
                // still renders them; grid highlighting requires ids, so
                // document that combination as preview-only.
                if (is_string($value) && filled($value)) {
                    $items[] = [
                        'id' => null,
                        'url' => $value,
                        'thumb' => $thumbnail->thumbnail($value, 480) ?: $value,
                        'name' => basename(parse_url($value, PHP_URL_PATH) ?: $value),
                        'note' => '',
                    ];
                }
            }

            if ($numericIds !== []) {
                $mediaById = MediaLibrary::query()
                    ->whereIn('id', $numericIds)
                    ->get()
                    ->keyBy('id');

                foreach ($numericIds as $id) {
                    $media = $mediaById->get($id);
                    if (! $media instanceof MediaLibrary || blank($media->url)) {
                        continue;
                    }

                    $url = (string) $media->url;
                    $items[] = [
                        'id' => $id,
                        'url' => $url,
                        'thumb' => $thumbnail->thumbnail($url, 480) ?: $url,
                        'name' => $media->original_name ?: basename($media->path),
                        'note' => (string) ($media->alt_text ?? ''),
                    ];
                }
            }

            return $items === [] ? null : $items;
        }

        if (blank($state)) {
            return null;
        }

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
                'selected_media_id' => $this->isMultiple()
                    ? array_map(fn ($id): string => (string) $id, Arr::wrap($this->getState()) ?: [])
                    : ($this->getState() !== null ? (string) $this->getState() : null),
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
                        MediaLibrary::TYPE_FILE => ['application/pdf', 'application/zip', 'application/x-rar-compressed', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'text/plain', 'text/csv'],
                        null => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'video/quicktime'],
                        default => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                    })
                    ->maxFiles(1)
                    ->maxSize((int) ((float) config('filament-media-library.max_size', 20) * 1024))
                    ->previewable(false)
                    ->imageEditor()
                    ->imagePreviewHeight('0')
                    ->panelLayout('integrated')
                    ->extraAttributes(['class' => 'fi-media-picker-file-upload fi-media-picker-file-upload-slim'])
                    ->extraFieldWrapperAttributes(['class' => 'fi-media-picker-upload-hidden'])
                    ->disk($this->getDisk())
                    ->directory(fn (Get $get): string => trim((string) config('filament-media-library.directory', 'media'), '/')
                        .'/library/'.$this->uploadFolderFromState($get('picker_folder')).'/'.($this->getMediaType() ?? 'file'))
                    ->visibility($this->getVisibility())
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
                            disk: $this->getDisk(),
                            visibility: $this->getVisibility(),
                        );

                        $set('selected_media_id', (string) $media->getKey());
                        $set('upload_new', null);
                    }),
                ViewField::make('selected_media_id')
                    ->hiddenLabel()
                    ->required(! $this->isMultiple())
                    ->live()
                    ->view('filament-media-library::media-picker-grid')
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
                            'is_multiple' => $this->isMultiple(),
                            'foldersUrl' => MediaFolderResource::getUrl('index'),
                        ];
                    }),
            ])
            ->action(function (array $data): void {
                $selected = $data['selected_media_id'] ?? null;

                if (! $this->isMultiple()) {
                    if (blank($selected)) {
                        $this->state(null);

                        return;
                    }

                    $this->state($this->resolveStateValue((int) $selected));

                    return;
                }

                $ids = collect(Arr::wrap($selected))
                    ->filter(fn ($id): bool => filled($id) && is_numeric($id))
                    ->map(fn ($id): int => (int) $id)
                    ->values();

                if ($ids->isEmpty()) {
                    $this->state([]);

                    return;
                }

                $this->state($ids->map(fn (int $id): int|string => $this->resolveStateValue($id))->all());
            });
    }

    protected function resolveStateValue(int $mediaId): int|string
    {
        if ($this->storeMode === 'url') {
            $media = MediaLibrary::query()->find($mediaId);

            return $media instanceof MediaLibrary ? (string) $media->url : $mediaId;
        }

        return $mediaId;
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
