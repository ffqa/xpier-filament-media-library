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
            ->mediaType('image'),  // 'image' | 'file' | 'video' | null (all)
    ]);
}
```

#### Store modes

| Mode | What gets stored | Use case |
|---|---|---|
| `id` (default) | `media_library.id` | Referential integrity; resolve the URL at render time |
| `url` | Resolved URL string | Legacy URL/path columns; decoupled from the library |

#### Media types

| `mediaType()` | Filter | Upload accept |
|---|---|---|
| `'image'` | images only | `image/jpeg, png, webp, gif` |
| `'file'` | files only | `pdf, zip, rar, doc(x), xls(x), ppt(x), txt, csv` |
| `'video'` | videos only | `mp4, webm, mov, avi, mkv` |
| `null` | everything | images + videos |

#### Single selection with a BelongsTo relationship (recommended)

```php
// Form: the field name is arbitrary; relationship() points at a model relation
MediaPicker::make('cover_image')
    ->relationship('featuredImage'),
```

```php
// Model side (post table needs a featured_image_id foreign key column)
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;

public function featuredImage(): BelongsTo
{
    return $this->belongsTo(MediaLibrary::class, 'featured_image_id');
}
```

On save the related media is `associate`d (or set to `null` when nothing is selected); on open the value is loaded from the relationship. The field itself never writes a column (`dehydrated(false)`).

```php
$post->featuredImage?->url;
```

#### Multiple selection with a BelongsToMany relationship (galleries)

```php
// Form
MediaPicker::make('gallery')
    ->multiple()
    ->relationship('galleryMedia')
    ->orderColumn('order'), // optional: pivot order column, written in selection order
```

```php
// Model side
use Xpier\FilamentMediaLibrary\Models\MediaLibrary;

public function galleryMedia(): BelongsToMany
{
    return $this->belongsToMany(MediaLibrary::class, 'post_media', 'post_id', 'media_id')
        ->withPivot('order')
        ->orderBy('order');
}
```

On save the relationship is `sync`ed (adds, removals and ordering in one pass); on open the existing selection is loaded automatically.

```php
$urls = $post->galleryMedia->pluck('url');
```

#### Multiple selection without a relationship (JSON column)

```php
MediaPicker::make('cover_images')
    ->multiple(), // state is an array of ids, written to the column directly
```

The column must be JSON and cast on the model:

```php
protected function casts(): array
{
    return ['cover_images' => 'array'];
}
```

#### Customizing the media query

```php
MediaPicker::make('cover_image')
    ->modifyMediaQueryUsing(function (Builder $query) {
        $query->where('size', '>', 1024);
    }),
```

#### Per-field disk and visibility

Uploads default to the global disk (`MEDIA_DISK`) and visibility (`MEDIA_VISIBILITY`). Override per field:

```php
MediaPicker::make('cover_image')
    ->disk('private_s3')        // uploads go to this disk
    ->visibility('private'),    // upload visibility (public | private)
```

#### Private write, public read (CDN / public domain)

Files live on a **private** disk (or private bucket) but are served through a **public CDN / domain**. By default `Storage::url()` returns the private address and public access 403s. Three options:

**Option 1 — per-disk public URL map (recommended for multiple disks)**

Publish the config, then map each disk to its public domain:

```php
'public_urls' => [
    'private_s3' => 'https://cdn.example.com',
    'r2' => 'https://pub-xxxx.r2.dev',
],
```

Every media `url` attribute on that disk resolves to `{mapped domain}/{path}`, regardless of the disk's visibility. Files stay on the private disk; each public domain serves them.

**Option 2 — single public URL prefix (simplest)**

```env
MEDIA_PUBLIC_URL=https://cdn.example.com
```

Used as a fallback for any disk without a `public_urls` mapping.

> Note: if the disk already has a public base URL configured (`AWS_URL` on S3, a public R2 bucket domain), `Storage::url()` already returns a public URL — nothing extra to configure.

**Option 3 — custom URL resolver (highest precedence)**

```php
// config/filament-media-library.php
'url_resolver' => \App\Support\MyUrlResolver::class,
```

```php
use Xpier\FilamentMediaLibrary\Support\MediaUrlResolver;

class MyUrlResolver implements MediaUrlResolver
{
    // Return null to fall back to the default Storage::disk()->url() behavior
    public function url(string $disk, string $path): ?string
    {
        return rtrim(config('cdn.base_url'), '/').'/'.ltrim($path, '/');
    }
}
```

Or signed (temporary) URLs for private reads:

```php
public function url(string $disk, string $path): ?string
{
    return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(60));
}
```

**Resolution chain:** `url_resolver` class → `public_urls[disk]` → `public_url` default → `Storage::url()`.

## Inserting media in RichEditor

Built on Filament's native RichEditor plugin mechanism (`RichContentPlugin`) — no third-party editor library:

```php
use Xpier\FilamentMediaLibrary\Filament\RichEditor\AttachMediaPlugin;
use Filament\Forms\Components\RichEditor;

RichEditor::make('content')
    ->plugins([
        AttachMediaPlugin::make(),
    ]),
```

An "Insert Media" toolbar button opens a modal with a MediaPicker (folder browse / search / upload); picking an image inserts a Tiptap image node at the cursor with the media's `alt_text`. The button highlights when an image is selected.

**Uploads inside the picker** go through `AdminMediaService`, land in the currently-browsed folder, and can be edited (crop/resize) before storing thanks to the built-in image editor.

> **Upload security:** `acceptedFileTypes()` is client-side only (FilePond). The package enforces a server-side extension whitelist (per media type, based on the MIME-detected extension — a file named `evil.php` with image content is stored as `.png`) and a size limit (`MEDIA_MAX_SIZE`, default 20 MB) in `AdminMediaService::storeUpload()`.

### 2. Media Library admin resource

Manage the platform library at `/admin/media-library`:

- Upload with image editor, pick folder + type, set alt-text/notes and custom properties
- Table: thumbnail preview, filename (searchable), folder badge, type badge, size, uploaded-at
- Filters: folder, type, trashed
- Deletion behavior is configurable (`MEDIA_DELETE_MODE`):
  - `soft` (default): delete moves the record to the trash (restorable via the Trashed filter); the physical file is removed by default so the public URL stops resolving immediately — set `MEDIA_DELETE_FILE_ON_DELETE=false` to keep files for restored records
  - `physical`: delete removes the record and the file right away, no trash

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
| `max_size` | `MEDIA_MAX_SIZE` | `20` | Max upload size in MB (server-side enforced) |
| `delete_mode` | `MEDIA_DELETE_MODE` | `soft` | Deletion mode: `soft` (trash, restorable) / `physical` (record + file removed immediately) |
| `delete_file_on_delete` | `MEDIA_DELETE_FILE_ON_DELETE` | `true` | Remove the physical file on soft delete |
| `image_process` | `MEDIA_COS_IMAGE_PROCESS` | `true` | COS image processing toggle |
| `thumbnail_provider` | `MEDIA_THUMBNAIL_PROVIDER` | `LocalThumbnailProvider` | Thumbnail URL generator class |
| `public_urls` | config file | `[]` | Per-disk public URL map (disk → CDN prefix) |
| `public_url` | `MEDIA_PUBLIC_URL` | *(empty)* | Global public URL prefix for unmapped disks |
| `url_resolver` | `MEDIA_URL_RESOLVER` | *(empty)* | Custom `MediaUrlResolver` class; highest precedence |
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

> **Note:** `CosThumbnailProvider` appends `?imageMogr2/...` to every image URL (including domains mapped via `public_urls` / `public_url`). Only enable it when all served domains are Tencent COS (or a COS-compatible CDN that understands `imageMogr2`); otherwise keep `LocalThumbnailProvider` or set `MEDIA_COS_IMAGE_PROCESS=false`.

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

## Testing

```bash
composer install        # installs testbench / phpunit
vendor/bin/phpunit      # runs the test suite
```

52 tests cover: models (soft delete file behavior, physical delete mode, event dispatch, URL resolution, folder paths and nesting validation), the upload service (server-side extension whitelist and size limit, disk/visibility/folder normalization), URL resolvers (per-disk map, global fallback, custom class), thumbnail providers (local passthrough, COS imageMogr2), and the MediaPicker component (multiple, relationship hook registration, per-field disk/visibility, selected-media resolution).

## How it compares

### vs spatie/laravel-medialibrary

Spatie is a **low-level storage layer** — it attaches files to your Eloquent models (`$model->addMedia(...)->toMediaCollection('gallery')`), generates conversions and responsive images, and is UI-agnostic. This package is a **Filament admin UI for a central library**. They solve different problems:

| Capability | spatie | xpier |
|---|---|---|
| Attach media to models | ✅ | Via stored `id`/`url` or `->relationship()` |
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
| `->relationship()` picker | ✅ | ✅ (BelongsTo + BelongsToMany) |
| `->multiple()` picker | ✅ | ✅ (with pivot ordering) |
| RichEditor attach button | ✅ | ✅ `AttachMediaPlugin` |
| Image editor (crop/resize) | ✅ | ✅ (built-in FileUpload) |
| Multi-disk per field | ✅ | ✅ `->disk()` |
| Signed URLs | ✅ Glide | ✅ custom `MediaUrlResolver` |
| Custom properties | ❌ | ✅ |

## Roadmap

- CI pipeline for the test suite (GitHub Actions)

## Publishing

This package is versioned via Git tags (`v1.x.x`). To release:

1. Tag the release: `git tag v1.0.0 && git push origin --tags`
2. Packagist picks it up automatically if you registered the GitHub repo via the **GitHub App** webhook; otherwise press "Update" on packagist.org.

## License

MIT — see [LICENSE.md](LICENSE.md).
