<?php

use CodeItamarJr\Attachments\Models\Attachment;

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk where attachments should be stored. This may point
    | to any disk defined in config/filesystems.php.
    |
    */
    'disk' => env('ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'public')),

    /*
    |--------------------------------------------------------------------------
    | Default Visibility
    |--------------------------------------------------------------------------
    |
    | Attachments may be stored as either public or private files. Public
    | files resolve with Storage::url(), while private files use
    | temporary URLs when accessed through the package helpers.
    |
    */
    'visibility' => env('ATTACHMENTS_VISIBILITY', Attachment::VISIBILITY_PUBLIC),

    /*
    |--------------------------------------------------------------------------
    | Uploader Model
    |--------------------------------------------------------------------------
    |
    | The model used by the Attachment::uploader() relationship. Leave this
    | aligned with your application's authenticatable user model.
    |
    */
    'uploader_model' => env('ATTACHMENTS_UPLOADER_MODEL', config('auth.providers.users.model')),

    /*
    |--------------------------------------------------------------------------
    | Uploader Foreign Key
    |--------------------------------------------------------------------------
    |
    | The attachments table column that stores the uploader identifier.
    |
    */
    'uploader_foreign_key' => env('ATTACHMENTS_UPLOADER_FOREIGN_KEY', 'uploaded_by'),

    /*
    |--------------------------------------------------------------------------
    | Base Directory
    |--------------------------------------------------------------------------
    |
    | Relative directory within the disk where attachments will be stored.
    |
    */
    'directory' => env('ATTACHMENTS_DIRECTORY', 'attachments'),

    /*
    |--------------------------------------------------------------------------
    | Private URL Lifetime
    |--------------------------------------------------------------------------
    |
    | When generating URLs for private attachments, the package creates a
    | temporary URL that expires after this many minutes.
    |
    */
    'private_url_ttl' => (int) env('ATTACHMENTS_PRIVATE_URL_TTL', Attachment::DEFAULT_PRIVATE_URL_TTL),
];
