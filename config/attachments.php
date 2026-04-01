<?php

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
    'visibility' => env('ATTACHMENTS_VISIBILITY', 'public'),

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
    'private_url_ttl' => (int) env('ATTACHMENTS_PRIVATE_URL_TTL', 5),
];
