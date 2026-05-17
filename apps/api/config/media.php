<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media storage disk
    |--------------------------------------------------------------------------
    |
    | Local development uses the "public" disk under storage/app/public/media.
    | Production can switch to s3 or another disk via MEDIA_DISK.
    |
    */

    'disk' => env('MEDIA_DISK', 'public'),

    'directory' => env('MEDIA_DIRECTORY', 'media'),

    'max_width' => (int) env('MEDIA_MAX_WIDTH', 1600),

    'max_height' => (int) env('MEDIA_MAX_HEIGHT', 1600),

    'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 82),

    'favicon_size' => (int) env('MEDIA_FAVICON_SIZE', 64),

    'logo_max_height' => (int) env('MEDIA_LOGO_MAX_HEIGHT', 120),

];
