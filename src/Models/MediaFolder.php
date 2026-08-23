<?php

namespace Xpier\FilamentMediaLibrary\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MediaFolder extends Model
{
    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'sort',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $folder): void {
            if (blank($folder->code) && filled($folder->name)) {
                $folder->code = Str::slug($folder->name) ?: 'folder-'.substr(md5($folder->name), 0, 8);
            }
            $folder->code = Str::of((string) $folder->code)
                ->lower()
                ->replaceMatches('/[^a-z0-9\-]+/', '-')
                ->trim('-')
                ->value() ?: 'folder';

            if ($folder->parent_id !== null) {
                $parent = $folder->parent_id === $folder->id
                    ? null
                    : self::query()->find($folder->parent_id);
                if ($parent instanceof self && $parent->parent_id !== null) {
                    throw new \InvalidArgumentException('Subfolders can only be created under a top-level folder.');
                }
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    public function mediaFilesQuery()
    {
        return MediaLibrary::query()
            ->library()
            ->where('folder', $this->storage_path);
    }

    public function getStoragePathAttribute(): string
    {
        $code = (string) $this->code;
        if ($this->parent_id === null) {
            return $code;
        }

        $parent = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();
        if ($parent instanceof self) {
            return $parent->storage_path.'/'.$code;
        }

        return $code;
    }

    /**
     * @return array<string, string>
     */
    public static function options(bool $activeOnly = true): array
    {
        $query = static::query()
            ->with('parent')
            ->orderBy('sort')
            ->orderBy('id');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $options = [];
        foreach ($query->get() as $folder) {
            if (! $folder instanceof self) {
                continue;
            }
            $path = $folder->storage_path;
            $label = $folder->parent_id === null
                ? $folder->name
                : '　└ '.$folder->name;
            $options[$path] = $label;
        }

        return $options;
    }

    public static function resolveStoragePath(string $folderKey): ?string
    {
        $key = Str::of($folderKey)->trim()->value();
        if ($key === '') {
            return null;
        }

        foreach (static::options(activeOnly: false) as $path => $name) {
            if ($path === $key) {
                return $path;
            }
        }

        return null;
    }

    public static function findByStoragePath(string $path): ?self
    {
        $path = Str::of($path)->trim('/')->value();
        if ($path === '') {
            return null;
        }

        return static::query()
            ->with('parent')
            ->get()
            ->first(fn (self $folder): bool => $folder->storage_path === $path);
    }

    /**
     * @return list<string>
     */
    public static function knownStoragePaths(bool $activeOnly = true): array
    {
        return array_keys(static::options($activeOnly));
    }

    /**
     * @return list<self>
     */
    public static function rootFolders(bool $activeOnly = true): array
    {
        $query = static::query()->whereNull('parent_id')->orderBy('sort')->orderBy('id');
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get()->all();
    }
}
