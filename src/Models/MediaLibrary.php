<?php

namespace Xpier\FilamentMediaLibrary\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaLibrary extends Model
{
    use SoftDeletes;

    public const TYPE_IMAGE = 'image';

    public const TYPE_FILE = 'file';

    public const TYPE_VIDEO = 'video';

    public const SOURCE_ADMIN = 'admin';

    protected $table = 'media_library';

    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'extension',
        'mime_type',
        'size',
        'type',
        'source',
        'folder',
        'alt_text',
        'custom_properties',
        'user_id',
    ];

    protected $appends = [
        'url',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'custom_properties' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Soft delete also removes the physical file by default, so a deleted
        // media URL stops resolving immediately. Set the
        // 'filament-media-library.delete_file_on_delete' config to false to
        // keep files for restorable records.
        static::deleting(function (self $media): void {
            if (! (bool) config('filament-media-library.delete_file_on_delete', true)) {
                return;
            }

            Storage::disk($media->disk)->delete($media->path);
        });

        static::forceDeleting(function (self $media): void {
            Storage::disk($media->disk)->delete($media->path);
        });

        static::created(fn (self $media) => \Xpier\FilamentMediaLibrary\Events\MediaUploaded::dispatch($media));
        static::deleted(fn (self $media) => \Xpier\FilamentMediaLibrary\Events\MediaDeleted::dispatch($media, $media->isForceDeleting()));
        static::restored(fn (self $media) => \Xpier\FilamentMediaLibrary\Events\MediaRestored::dispatch($media));
    }

    public static function defaultDisk(): string
    {
        return (string) config('filament-media-library.disk', 'public');
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_IMAGE => __('filament-media-library::media-library.types.image'),
            self::TYPE_FILE => __('filament-media-library::media-library.types.file'),
            self::TYPE_VIDEO => __('filament-media-library::media-library.types.video'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function adminFolderOptions(): array
    {
        $options = MediaFolder::options(activeOnly: true);

        return $options !== [] ? $options : [
            'general' => __('filament-media-library::media-library.folders.general'),
            'pets' => __('filament-media-library::media-library.folders.pets'),
            'articles' => __('filament-media-library::media-library.folders.articles'),
            'banners' => __('filament-media-library::media-library.folders.banners'),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function getUrlAttribute(): ?string
    {
        $disk = (string) $this->disk;
        $path = ltrim((string) $this->path, '/');

        if ($disk === '' || $path === '') {
            return null;
        }

        $resolver = app(\Xpier\FilamentMediaLibrary\Support\MediaUrlResolver::class);
        $resolved = $resolver->url($disk, $path);

        if (filled($resolved)) {
            return $resolved;
        }

        return Storage::disk($disk)->url($path);
    }

    public function isLibrary(): bool
    {
        return $this->source === self::SOURCE_ADMIN;
    }

    public function folderLabel(): string
    {
        return $this->folder ?? __('filament-media-library::media-library.media_library.uncategorized');
    }

    public function scopeLibrary(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_ADMIN);
    }
}
