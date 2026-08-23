<?php

namespace Xpier\FilamentMediaLibrary\Support;

/**
 * Resolves the public URL of a stored media file.
 *
 * Useful for "private write, public read" setups where files are stored on a
 * private disk but served through a public CDN / custom domain. Return null to
 * fall back to the default `Storage::disk($disk)->url($path)` behavior.
 */
interface MediaUrlResolver
{
    public function url(string $disk, string $path): ?string;
}
