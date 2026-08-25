<?php

namespace Xpier\FilamentMediaLibrary\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Xpier\FilamentMediaLibrary\Events\MediaDeleted;
use Xpier\FilamentMediaLibrary\Events\MediaRestored;
use Xpier\FilamentMediaLibrary\Events\MediaUploaded;
use Xpier\FilamentMediaLibrary\Support\MediaUrlResolver;

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
        // The physical file is removed after the database delete succeeded
        // (deleted event), so a failed DELETE never loses the file. It is
        // removed on every delete (soft or hard) unless
        // 'filament-media-library.delete_file_on_delete' is false.
        static::deleted(function (self $media): void {
            if ($media->isForceDeleting() || (bool) config('filament-media-library.delete_file_on_delete', true)) {
                try {
                    Storage::disk($media->disk)->delete($media->path);
                } catch (Throwable $exception) {
                    // The record is gone but the file may remain; report it
                    // so orphaned files stay traceable.
                    report($exception);
                }
            }

            MediaDeleted::dispatch($media, $media->isForceDeleting());
        });

        static::created(fn (self $media) => MediaUploaded::dispatch($media));
        static::restored(fn (self $media) => MediaRestored::dispatch($media));
    }

    public static function defaultDisk(): string
    {
        return (string) config('filament-media-library.disk', 'public');
    }

    /**
     * Deletion mode of the "delete" action: 'soft' (default, restorable)
     * or 'physical' (record and file removed immediately).
     */
    public static function deletionMode(): string
    {
        return (string) config('filament-media-library.delete_mode', 'soft');
    }

    /**
     * In 'physical' mode a plain delete() also removes the record and its
     * physical file immediately (force delete), so every caller — the
     * resource actions, bulk actions and programmatic deletes — respects
     * the configured deletion mode.
     *
     * The isForceDeleting() guard prevents recursion: SoftDeletes::forceDelete()
     * calls $this->delete() internally after setting the forceDeleting flag.
     */
    public function delete(): ?bool
    {
        if (static::deletionMode() === 'physical' && ! $this->isForceDeleting()) {
            return $this->forceDelete();
        }

        return parent::delete();
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
        return $this->belongsTo(
            (string) config('filament-media-library.user_model', config('auth.providers.users.model', User::class))
        );
    }

    public function getUrlAttribute(): ?string
    {
        $disk = (string) $this->disk;
        $path = ltrim((string) $this->path, '/');

        if ($disk === '' || $path === '') {
            return null;
        }

        $resolver = app(MediaUrlResolver::class);
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
