<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media storage disk
    |--------------------------------------------------------------------------
    |
    | Local development uses the "public" disk under storage/app/public/media.
    | Deployments should use object storage: MEDIA_DISK=s3 works with any
    | S3-compatible store (AWS S3, Cloudflare R2, Backblaze B2, MinIO) — see
    | the "s3" disk in config/filesystems.php and infra/deploy.md.
    |
    */

    'disk' => env('MEDIA_DISK', 'public'),

    /*
    | Visibility every upload is written with. On S3 "public" sends the
    | public-read ACL. Buckets that refuse object ACLs (AWS "bucket owner
    | enforced", the default for new buckets; R2) need "private" here and
    | public read granted on the media prefix by bucket policy instead.
    */
    'visibility' => env('MEDIA_VISIBILITY', 'public'),

    /*
    | Every upload gets a fresh random filename and is never rewritten in
    | place (a replacement is a new file), so a URL's bytes never change and
    | browsers/CDNs may cache them for good. Object stores send this back as
    | the Cache-Control response header; the local disk ignores it.
    */
    'cache_control' => env('MEDIA_CACHE_CONTROL', 'public, max-age=31536000, immutable'),

    'directory' => env('MEDIA_DIRECTORY', 'media'),

    'max_width' => (int) env('MEDIA_MAX_WIDTH', 1600),

    'max_height' => (int) env('MEDIA_MAX_HEIGHT', 1600),

    'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 82),

    'favicon_size' => (int) env('MEDIA_FAVICON_SIZE', 64),

    'logo_max_height' => (int) env('MEDIA_LOGO_MAX_HEIGHT', 120),

];
