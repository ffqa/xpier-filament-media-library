<?php

use Xpier\FilamentMediaLibrary\Support\Providers\LocalThumbnailProvider;

return [

    'disk' => env('MEDIA_DISK', env('AWS_BUCKET') ? 's3' : 'public'),

    'directory' => env('MEDIA_DIRECTORY', 'media'),

    'visibility' => env('MEDIA_VISIBILITY', 'public'),

    /** Max upload size in megabytes (server-side enforced). */
    'max_size' => (float) env('MEDIA_MAX_SIZE', 20),

    /**
     * Deletion mode of the "delete" action on the Media Library resource.
     *
     * - 'soft' (default): the record is soft-deleted (visible under the
     *   Trashed filter, restorable). Whether the physical file is removed
     *   is controlled by 'delete_file_on_delete'.
     * - 'physical': the record and its physical file are deleted immediately.
     */
    'delete_mode' => env('MEDIA_DELETE_MODE', 'soft'),

    /**
     * Whether a soft delete also removes the physical file from the disk.
     * Enabled by default so deleted media URLs stop resolving immediately;
     * disable to keep files so restored records keep working URLs.
     */
    'delete_file_on_delete' => filter_var(
        env('MEDIA_DELETE_FILE_ON_DELETE', true),
        FILTER_VALIDATE_BOOLEAN,
    ),

    'image_process' => filter_var(
        env('MEDIA_COS_IMAGE_PROCESS', true),
        FILTER_VALIDATE_BOOLEAN,
    ),

    'thumbnail_provider' => env('MEDIA_THUMBNAIL_PROVIDER', LocalThumbnailProvider::class),

    /**
     * Public URL resolution for "private write, public read" setups.
     *
     * - 'public_urls': per-disk public base URL map. Files stay on a private
     *   disk (or private bucket) but are served through the mapped public
     *   CDN / bucket domain. Example:
     *       'public_urls' => [
     *           'private_s3' => 'https://cdn.example.com',
     *           'r2' => 'https://pub-xxxx.r2.dev',
     *       ],
     * - 'public_url': fallback base URL used for disks without a mapping
     *   (kept as MEDIA_PUBLIC_URL for simple single-CDN setups).
     * - Note: with CosThumbnailProvider enabled, every mapped domain must be
     *   COS (or COS-compatible), since imageMogr2 is appended to image URLs.
     * - 'url_resolver': custom class implementing MediaUrlResolver. Takes
     *   precedence over both; return null to fall back to the default
     *   Storage::disk()->url() behavior.
     */
    'public_urls' => [
        // 'private_s3' => 'https://cdn.example.com',
    ],

    'public_url' => env('MEDIA_PUBLIC_URL'),

    'url_resolver' => env('MEDIA_URL_RESOLVER'),

    'navigation_group' => env('MEDIA_NAVIGATION_GROUP'),

    'navigation_sort' => env('MEDIA_NAVIGATION_SORT', 5),

    'navigation_badge' => filter_var(
        env('MEDIA_NAVIGATION_BADGE', true),
        FILTER_VALIDATE_BOOLEAN,
    ),

    'default_module' => env('MEDIA_DEFAULT_MODULE', 'general'),

    /**
     * User model used by the media.user() relation. Falls back to the
     * application's auth provider model.
     */
    'user_model' => env('MEDIA_USER_MODEL'),

    'folder_presets' => [],

];
