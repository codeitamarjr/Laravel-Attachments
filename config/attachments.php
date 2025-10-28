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
    | Base Directory
    |--------------------------------------------------------------------------
    |
    | Relative directory within the disk where attachments will be stored.
    |
    */
    'directory' => env('ATTACHMENTS_DIRECTORY', 'attachments'),
];
