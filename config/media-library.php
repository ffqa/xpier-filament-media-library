<?php

use Xpier\FilamentMediaLibrary\Support\Providers\LocalThumbnailProvider;

return [

    'disk' => env('MEDIA_DISK', env('AWS_BUCKET') ? 's3' : 'public'),

    'directory' => env('MEDIA_DIRECTORY', 'media'),

    'visibility' => env('MEDIA_VISIBILITY', 'public'),

    'image_process' => filter_var(
        env('MEDIA_COS_IMAGE_PROCESS', true),
        FILTER_VALIDATE_BOOLEAN,
    ),

    'thumbnail_provider' => env('MEDIA_THUMBNAIL_PROVIDER', LocalThumbnailProvider::class),

    /**
     * Public URL resolution for "private write, public read" setups.
     *
     * - 'public_url': fixed base URL (CDN / public bucket domain) prepended to
     *   the stored path. Files stay on a private disk but are served publicly.
     * - 'url_resolver': custom class implementing MediaUrlResolver. Takes
     *   precedence over 'public_url'; returns null to fall back to the
     *   default Storage::disk()->url() behavior.
     */
    'public_url' => env('MEDIA_PUBLIC_URL'),

    'url_resolver' => env('MEDIA_URL_RESOLVER'),

    'navigation_group' => env('MEDIA_NAVIGATION_GROUP'),

    'navigation_sort' => env('MEDIA_NAVIGATION_SORT', 5),

    'navigation_badge' => filter_var(
        env('MEDIA_NAVIGATION_BADGE', true),
        FILTER_VALIDATE_BOOLEAN,
    ),

    'default_module' => env('MEDIA_DEFAULT_MODULE', 'general'),

    'folder_presets' => [],

];
