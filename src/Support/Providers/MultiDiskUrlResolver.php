<?php

namespace Xpier\FilamentMediaLibrary\Support\Providers;

use Xpier\FilamentMediaLibrary\Support\MediaUrlResolver;

/**
 * Resolves URLs against per-disk public base URLs, falling back to a
 * default base URL and finally to null (which makes the model use
 * Storage::disk()->url()).
 *
 * Use case: files are written to private disks (private write) but served
 * through public CDN / bucket domains (public read). Each disk can map to
 * its own domain.
 */
class MultiDiskUrlResolver implements MediaUrlResolver
{
    /**
     * @param  array<string, string>  $publicUrls  Disk name => public base URL
     */
    public function __construct(
        protected array $publicUrls = [],
        protected ?string $defaultPublicUrl = null,
    ) {}

    public function url(string $disk, string $path): ?string
    {
        $base = $this->publicUrls[$disk] ?? $this->defaultPublicUrl;

        if (blank($base)) {
            return null;
        }

        return rtrim($base, '/').'/'.ltrim($path, '/');
    }
}
