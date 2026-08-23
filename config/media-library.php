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

    'navigation_group' => env('MEDIA_NAVIGATION_GROUP'),

    'navigation_sort' => env('MEDIA_NAVIGATION_SORT', 5),

    'navigation_badge' => filter_var(
        env('MEDIA_NAVIGATION_BADGE', true),
        FILTER_VALIDATE_BOOLEAN,
    ),

    'default_module' => env('MEDIA_DEFAULT_MODULE', 'general'),

    'folder_presets' => [],

];
