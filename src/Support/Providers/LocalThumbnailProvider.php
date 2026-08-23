<?php

namespace Xpier\FilamentMediaLibrary\Support\Providers;

use Xpier\FilamentMediaLibrary\Support\ThumbnailProvider;

/**
 * Default provider: returns the original URL with no processing.
 * Use this when the storage host does not support on-the-fly image transforms.
 */
class LocalThumbnailProvider implements ThumbnailProvider
{
    public function thumbnail(?string $url, int $maxEdge = 400, int $quality = 75): ?string
    {
        return $url;
    }
}
