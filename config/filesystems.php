<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            // Root-relative, not built from APP_URL. An absolute URL bakes in
            // whatever host:port APP_URL happens to name, so a page served from
            // anywhere else (a preview domain, 127.0.0.1:8000, a different
            // vhost) points its images at a *different origin* — which is
            // exactly how the journalist's portrait ended up 404ing.
            //
            // `serve` registers a /storage/{path} route handled by Laravel, so
            // public files are served even when the web server cannot follow
            // `public/storage`. That matters on Windows, where
            // `storage:link` creates a directory *junction* that Apache does not
            // traverse — the site loads and every image 404s. Where the symlink
            // does work (nginx, `artisan serve`) the web server answers first
            // and this route is never reached, so it is purely a fallback.
            //
            // MEDIA_URL exists for the deployment this string cannot describe:
            // an install under a subdirectory (http://localhost/ehb/public,
            // which is how XAMPP and most shared hosts serve a project). A
            // root-relative `/storage/…` is resolved by the browser against the
            // *domain root*, where there is nothing to serve, so every page
            // image 404s even though the file is on disk, the /storage route is
            // registered, and the admin panel — whose own assets come from
            // APP_URL — looks perfectly fine. The diagnostic command
            // (`php artisan media:doctor`) detects that combination; the fix is
            // one line in .env:
            //
            //     MEDIA_URL=/ehb/public/storage
            //
            // Laravel has ASSET_URL for the same reason and the same shape. At
            // a normal domain root the default is already correct, which is why
            // this stays opt-in rather than being derived from APP_URL.
            //
            // A prefixed value also moves the route Laravel registers for this
            // disk, so bootstrap/app.php registers the reachable form of it as
            // well: that way the files are served in a subdirectory layout even
            // when the web server cannot follow `public/storage`.
            'url' => env('MEDIA_URL', '/storage'),
            'visibility' => 'public',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
