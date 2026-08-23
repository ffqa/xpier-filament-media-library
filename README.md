<p align="center">
    <h1 align="center">Filament Media Library</h1>
    <p align="center">
        A Filament-native media library: folder browser, inline upload, search, soft deletes, and a drop-in <code>MediaPicker</code> form field.
    </p>
</p>

<p align="center">
    <a href="LICENSE.md"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT License"></a>
    <img src="https://img.shields.io/badge/filament-v5-orange.svg" alt="Filament v5">
    <img src="https://img.shields.io/badge/laravel-v12-red.svg" alt="Laravel 12">
    <img src="https://img.shields.io/badge/php-%5E8.2-8892BF.svg" alt="PHP ^8.2">
</p>

---

## What is this?

`xpier/filament-media-library` is a **self-contained media library for Filament v5**. Unlike [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary) (a low-level storage library) or the [official Filament spatie plugin](https://filamentphp.com/plugins/spatie-laravel-media-library) (a form field that stores files on your models), this package manages a **central platform media library** in the admin panel:

- A **Media Library** resource — upload, preview, organize, soft-delete, restore, force-delete
- A **Media Folders** resource — two-level folder hierarchy mapped to storage paths
- A **`MediaPicker`** form field — a modal browser (folders + search + inline upload) usable from *any* Filament form
- **Events**, **custom properties**, **thumbnail providers**, **i18n**, **zero build dependencies**

```
┌────────────────────────────────────────────────────────────┐
│  Admin panel                                               │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Media Library (resource)                            │  │
│  │  ┌──────────────┐   ┌──────────────┐                │  │
│  │  │ uploads      │   │ folders      │                │  │
│  │  │ preview      │   │ hierarchy    │                │  │
│  │  │ soft-delete  │   │ storage path │                │  │
│  │  └──────────────┘   └──────────────┘                │  │
│  └──────────────────────────────────────────────────────┘  │
│  Any resource form:                                        │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  MediaPicker::make('cover_image')  [Pick from library]│  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────┘
```

## Features

- **Media Library admin resource** — drag-and-drop upload with image editor (crop/resize), live preview, table with thumbnails/folder/type/size badges, folder + type filters, soft delete with Trashed filter, restore & force-delete
- **Media Folders admin resource** — two-level hierarchy, slug-based storage paths (`media/library/{parent}/{code}/…`), sort order, per-folder activation
- **`MediaPicker` form field** — modal browser with folder navigation, breadcrumbs, **debounced search** across filenames/alt-text, upload-to-current-folder, single-select with preview & remove
- **Custom properties** — key-value metadata per media record (JSON column, edited via a KeyValue field)
- **Events** — `MediaUploaded`, `MediaDeleted` (with `force` flag), `MediaRestored`
- **ThumbnailProvider abstraction** — pluggable thumbnail URL generation (Tencent COS `imageMogr2` included; local pass-through by default)
- **i18n** — English + Simplified Chinese shipped; every UI string translatable
- **No build step** — all picker CSS is inline; no Vite/Tailwind theme requirement
- **Configurable navigation** — group, sort, badge via config/env or plugin methods

## Requirements

| Component | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^12.0 |
| Filament | ^5.3 |

## Installation

```bash
composer require xpier/filament-media-library
```

The package auto-discovers its service provider. Run migrations (they auto-load from the package):

```bash
php artisan migrate
```

Register the plugin in your panel provider (`app/Providers/Filament/AdminPanelProvider.php`):

```php
use Xpier\FilamentMediaLibrary\Filament\MediaLibraryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            MediaLibraryPlugin::make()
                ->navigationGroup('Content'), // optional — see Configuration
        ]);
}
```

That's it. You now have **Media Library** and **Media Folders** in the sidebar.

## Usage

### 1. MediaPicker form field

The main entry point for editors. Drop it into any Filament resource form:

```php
use Xpier\FilamentMediaLibrary\Components\MediaPicker;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        MediaPicker::make('cover_image')
            ->label('Cover Image')
            ->module('articles')   // default folder code when uploading
            ->mediaType('image')   // 'image' | 'file' | 'video' | null (all)
            ->storeMode('id'),     // 'id' (default) | 'url'
    ]);
}
```

**Store modes**

| Mode | What gets stored | Use case |
|---|---|---|
| `id` (default) | `media_library.id` | Referential integrity; resolve the URL at render time |
| `url` | Resolved URL string | Legacy URL/path columns; decoupled from the library |

**Media types**

| `mediaType()` | Filter | Upload accept |
|---|---|---|
| `'image'` | images only | `image/jpeg, png, webp, gif` |
| `'file'` | files only | — |
| `'video'` | videos only | `video/mp4, webm, quicktime` |
| `null` | everything | images + videos |

**Uploads inside the picker** go through `AdminMediaService`, land in the currently-browsed folder, and can be edited (crop/resize) before storing thanks to the built-in image editor.

**Reading the value back**

```php
// storeMode('id') — resolve via the model:
$media = MediaLibrary::find($post->cover_image);
$url = $media?->url;

// storeMode('url') — already a URL string:
$url = $post->cover_image;
```

### 2. Media Library admin resource

Manage the platform library at `/admin/media-library`:

- Upload with image editor, pick folder + type, set alt-text/notes and custom properties
- Table: thumbnail preview, filename (searchable), folder badge, type badge, size, uploaded-at
- Filters: folder, type, trashed
- Actions: soft delete → restore → force-delete (physical file is only removed on force-delete)

### 3. Media Folders admin resource

Manage the folder hierarchy at `/admin/media-folders`:

| Field | Description |
|---|---|
| Name | Display label |
| Code | English slug used in storage paths |
| Parent | Optional; subfolders nest one level deep |
| Sort | Display order |
| Active | Toggle picker visibility |

Storage path is derived: `general` → `media/library/general/…`, child `dogs` under `pets` → `media/library/pets/dogs/…`.

### 4. Events

Listen for library activity to keep other systems in sync:

```php
use Xpier\FilamentMediaLibrary\Events\MediaUploaded;

// In a service provider or EventServiceProvider:
Event::listen(function (MediaUploaded $event): void {
    $media = $event->media;
    // e.g. notify a moderation queue, purge a CDN cache, update search index
});
```

| Event | Payload | Fires when |
|---|---|---|
| `MediaUploaded` | `MediaLibrary $media` | Record created (picker uploads, admin uploads, programmatic) |
| `MediaDeleted` | `MediaLibrary $media, bool $force` | Record deleted; `$force = true` if physical file was removed |
| `MediaRestored` | `MediaLibrary $media` | Soft-deleted record restored |

## Configuration

Publish the config to customize:

```bash
php artisan vendor:publish --tag="filament-media-library-config"
```

| Key | Env var | Default | Description |
|---|---|---|---|
| `disk` | `MEDIA_DISK` | `s3` if `AWS_BUCKET`, else `public` | Filesystem disk |
| `directory` | `MEDIA_DIRECTORY` | `media` | Root directory |
| `visibility` | `MEDIA_VISIBILITY` | `public` | File visibility |
| `image_process` | `MEDIA_COS_IMAGE_PROCESS` | `true` | COS image processing toggle |
| `thumbnail_provider` | `MEDIA_THUMBNAIL_PROVIDER` | `LocalThumbnailProvider` | Thumbnail URL generator class |
| `navigation_group` | `MEDIA_NAVIGATION_GROUP` | *(lang file)* | Nav group label; `null` → locale-aware fallback |
| `navigation_sort` | `MEDIA_NAVIGATION_SORT` | `5` | Nav sort order |
| `navigation_badge` | `MEDIA_NAVIGATION_BADGE` | `true` | Show media count badge |
| `default_module` | `MEDIA_DEFAULT_MODULE` | `general` | Default upload folder |
| `folder_presets` | — | `[]` | Reserved for seeding |

**Navigation group resolution order:** plugin method → env var → config file → language file (`系统管理`/`System`).

## Thumbnail Providers

Implement `Xpier\FilamentMediaLibrary\Support\ThumbnailProvider` to control how thumbnails are generated:

```php
interface ThumbnailProvider
{
    public function thumbnail(?string $url, int $maxEdge = 400, int $quality = 75): ?string;
}
```

| Provider | Behavior |
|---|---|
| `LocalThumbnailProvider` (default) | Returns the original URL — use with R2, local disks, or any CDN without server-side processing |
| `CosThumbnailProvider` | Tencent COS `imageMogr2` thumbnails |

```env
MEDIA_THUMBNAIL_PROVIDER=Xpier\FilamentMediaLibrary\Support\Providers\CosThumbnailProvider
```

Custom providers are bound through the container (a singleton), so resolving is cheap:

```php
$this->app->bind(
    \Xpier\FilamentMediaLibrary\Support\ThumbnailProvider::class,
    \App\Support\MyThumbnailProvider::class,
);
```

## Translations

Ship `en` and `zh_CN`. Publish and translate:

```bash
php artisan vendor:publish --tag="filament-media-library-lang"
```

Files land in `lang/vendor/filament-media-library/{locale}/media-library.php`. Add a new locale by creating the matching file there.

## Views

```bash
php artisan vendor:publish --tag="filament-media-library-views"
```

| View | Purpose |
|---|---|
| `media-picker.blade.php` | Field shell: preview, remove, picker action |
| `media-picker-grid.blade.php` | Modal browser: toolbar, breadcrumbs, search, tiles (all CSS inline) |

## How it compares

### vs spatie/laravel-medialibrary

Spatie is a **low-level storage layer** — it attaches files to your Eloquent models (`$model->addMedia(...)->toMediaCollection('gallery')`), generates conversions and responsive images, and is UI-agnostic. This package is a **Filament admin UI for a central library**. They solve different problems:

| Capability | spatie | xpier |
|---|---|---|
| Attach media to models | ✅ | Via stored `id`/`url` |
| Named collections | ✅ | Folder hierarchy instead |
| Conversions / responsive images | ✅ (on-disk derived files) | On-demand via `ThumbnailProvider` |
| Admin management UI | Pro version only | ✅ built-in |
| Folder browser picker | Pro version only | ✅ built-in |
| Events | ✅ | ✅ (3 core events) |
| i18n | — | ✅ en/zh_CN |

**When to use which:** want per-model media with derived images → spatie (+ the official Filament plugin). Want a central, folder-organized platform library with a picker → this package. They can coexist.

### vs the official `filament/spatie-laravel-media-library-plugin`

That plugin is a thin form-field bridge to spatie (`SpatieMediaLibraryFileUpload`). It has no management resources and no shared library. This package is the opposite: management resources + shared library + picker. If you don't need spatie's conversions, you don't need that plugin's dependency weight.

### vs awcodes/filament-curator

Curator is the closest competitor — a Filament media manager with picker, relationships, multi-select, RichEditor integration, and Glide-signed URLs.

| Capability | curator | xpier |
|---|---|---|
| Management resource | ✅ | ✅ |
| Folder hierarchy | ❌ (path generators only) | ✅ two-level |
| Pick modal + search | ✅ | ✅ |
| Soft deletes | ❌ | ✅ |
| `->relationship()` picker | ✅ | ❌ *roadmap* |
| `->multiple()` picker | ✅ | ❌ *roadmap* |
| RichEditor attach button | ✅ | ❌ *roadmap* |
| Image editor (crop/resize) | ✅ | ✅ (built-in FileUpload) |
| Multi-disk per field | ✅ | ❌ *roadmap* |
| Signed URLs | ✅ Glide | ❌ (disk URL) |
| Custom properties | ❌ | ✅ |

## Roadmap

- `->relationship()` support on `MediaPicker` (store id, auto-associate on save)
- `->multiple()` multi-select picker with ordering
- RichEditor "attach media" plugin
- Per-field `disk()` override
- Signed/expiring URLs for private disks
- Test suite + CI (Pint + Pest)

## Publishing

This package is versioned via Git tags (`v1.x.x`). To release:

1. Tag the release: `git tag v1.0.0 && git push origin --tags`
2. Packagist picks it up automatically if you registered the GitHub repo via the **GitHub App** webhook; otherwise press "Update" on packagist.org.

## License

MIT — see [LICENSE.md](LICENSE.md).
