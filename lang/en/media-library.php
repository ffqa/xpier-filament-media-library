<?php

return [

    'navigation' => [
        'group' => 'System',
    ],

    'resources' => [
        'media_library' => [
            'navigation_label' => 'Media Library',
            'model_label' => 'Media File',
            'plural_label' => 'Media Library',
        ],

        'media_folder' => [
            'navigation_label' => 'Media Folders',
            'model_label' => 'Media Folder',
            'plural_label' => 'Media Folders',
        ],
    ],

    'media_library' => [
        'upload_section' => 'Upload to Platform Library',
        'upload_section_description' => 'Platform media is centrally managed. For article/ad images, use the "Pick from Media Library" modal (you can upload within the modal).',
        'upload_file' => 'Upload File',
        'preview' => 'Preview',
        'no_preview' => 'No preview',
        'folder' => 'Platform Folder',
        'folder_helper' => 'Manage folders in the "Media Folders" page.',
        'type' => 'Type',
        'alt_text' => 'Alt Text / Note',
        'custom_properties' => 'Custom Properties',
        'custom_properties_helper' => 'Additional key-value metadata for frontend display or business logic.',
        'manage_folders' => 'Manage Folders',
        'upload_to_platform' => 'Upload to Platform',
        'trashed_filter' => 'Deleted',
        'size' => 'Size',
        'uploaded_at' => 'Uploaded',
        'uncategorized' => 'Uncategorized',
    ],

    'upload' => [
        'too_large' => 'The file exceeds the maximum allowed size of :size MB.',
        'type_not_allowed' => 'File type :type is not allowed.',
    ],

    'media_folder' => [
        'parent_folder' => 'Parent Folder',
        'parent_helper' => 'Leave empty for a top-level category; subfolders can only be nested one level deep.',
        'name' => 'Folder Name',
        'code' => 'Code',
        'code_helper' => 'English short name; disk path is media/library/{category}/{code}/…',
        'sort' => 'Sort Order',
        'is_active' => 'Active',
        'storage_path' => 'Storage Path',
        'files_count' => 'Files',
        'updated_at' => 'Updated',
    ],

    'picker' => [
        'pick_from_library' => 'Pick from Media Library',
        'modal_heading' => 'Platform Media Library',
        'modal_description' => 'Browse like a file system: click a folder to enter, click "Up" to go back. You can upload to the current folder.',
        'confirm' => 'Confirm Selection',
        'cancel' => 'Cancel',
        'remove' => 'Remove',
        'no_cover' => 'No image',
        'hint' => 'Pick images from the platform media library or upload within the modal. Do not upload elsewhere.',
        'all' => 'All',
        'root' => 'Folders',
        'up' => 'Up',
        'search' => 'Search',
        'search_placeholder' => 'Search filename / note…',
        'search_results' => 'Search: :keyword',
        'search_result_count' => 'Search results: :count item(s). Click an image to select.',
        'media_count' => 'Media: :count item(s) (max 100). Click a folder to enter; click an image to select. Hover for name/note.',
        'empty' => 'Current folder is empty. Upload images or switch folders via breadcrumbs.',
        'upload_to_current' => 'Upload to current folder',
        'upload_disabled_all' => 'Cannot upload in "All" view, please enter a specific folder',
        'upload_disabled_search' => 'Cannot upload in search mode',
        'manage_folders' => 'Manage Folders',
        'items_count' => ':count items',
        'selected_count' => ':count selected',
        'selected_count_units' => ' selected',
    ],

    'types' => [
        'image' => 'Image',
        'file' => 'File',
        'video' => 'Video',
    ],

    'folders' => [
        'general' => 'General',
        'pets' => 'Pets',
        'articles' => 'Articles',
        'banners' => 'Banners',
    ],

    'rich_editor' => [
        'attach_media' => 'Insert Media',
        'attach_media_heading' => 'Insert image from media library',
        'select_media' => 'Select image',
        'insert' => 'Insert',
    ],

];
