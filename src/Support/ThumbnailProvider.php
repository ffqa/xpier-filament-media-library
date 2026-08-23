<?php

namespace Xpier\FilamentMediaLibrary\Support;

interface ThumbnailProvider
{
    /**
     * Build a thumbnail URL for the given source URL.
     *
     * Implementations should return the original URL unchanged when
     * the host does not support on-the-fly image processing.
     */
    public function thumbnail(?string $url, int $maxEdge = 400, int $quality = 75): ?string;
}
