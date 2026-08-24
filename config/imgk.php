<?php

return [

    // Hard safety limits for the transformation proxy.
    'max_source_bytes' => env('IMGK_MAX_SOURCE_BYTES', 30 * 1024 * 1024),
    'max_source_pixels' => env('IMGK_MAX_SOURCE_PIXELS', 50_000_000),
    'max_output_dimension' => env('IMGK_MAX_OUTPUT_DIMENSION', 8192),
    'fetch_timeout' => env('IMGK_FETCH_TIMEOUT', 15),
    'max_redirects' => env('IMGK_MAX_REDIRECTS', 3),

    'max_upload_kb' => env('IMGK_MAX_UPLOAD_KB', 30 * 1024),

    // Optional allowlist of source domains ("" = open, fair-use throttled).
    'allowed_hosts' => array_filter(explode(',', (string) env('IMGK_ALLOWED_HOSTS', ''))),

    'cache_dir' => env('IMGK_CACHE_DIR', 'imgk-cache'),
    'uploads_dir' => env('IMGK_UPLOADS_DIR', 'imgk-uploads'),
    'cache_max_age_days' => env('IMGK_CACHE_MAX_AGE_DAYS', 30),

    'accepted_mimes' => [
        'image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif',
        'image/heic', 'image/heif', 'image/bmp', 'image/tiff',
        'image/x-icon', 'image/vnd.microsoft.icon', 'application/pdf',
    ],

    // Named recipes. Explicit query params always override the preset values.
    'presets' => [
        'avatar' => ['w' => 256, 'h' => 256, 'fit' => 'cover', 'radius' => 'max', 'output' => 'webp', 'strip' => 'true'],
        'produit' => ['w' => 1200, 'h' => 1200, 'fit' => 'contain', 'bg' => 'ffffff', 'output' => 'auto', 'optimized' => 'true'],
        'scan' => ['conversion' => 'scan', 'output' => 'jpg', 'q' => 80, 'max' => 2000],
        'social' => ['w' => 1200, 'h' => 630, 'fit' => 'cover', 'output' => 'jpg', 'q' => 85, 'strip' => 'true'],
        'compress' => ['maxsize' => '2mb', 'optimized' => 'true', 'strip' => 'true'],
        'thumbnail' => ['w' => 320, 'h' => 320, 'fit' => 'cover', 'output' => 'webp', 'q' => 75, 'strip' => 'true'],
    ],

    'favicon_sizes' => [16, 32, 48, 180, 192, 512],

];
