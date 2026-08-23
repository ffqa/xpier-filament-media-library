<?php

namespace Xpier\FilamentMediaLibrary\Support\Providers;

use Xpier\FilamentMediaLibrary\Support\MediaUrlResolver;

/**
 * Resolves URLs against a fixed public base URL (e.g. a CDN domain or an
 * R2 public bucket domain). File paths are appended to the base URL, so the
 * underlying disk may stay private while content is served publicly.
 */
class CdnUrlResolver implements MediaUrlResolver
{
    public function __construct(protected string $baseUrl)
    {
    }

    public function url(string $disk, string $path): ?string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }
}
