<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'seed' => [
            'driver' => 'local',
            'root' => database_path(),
            'throw' => false,
        ],

        // 'local' => [
        //     'driver' => 'local',
        //     'root' => storage_path('app'),
        //     'throw' => false,
        // ],

        // 'public' => [
        //     'driver' => 'local',
        //     'root' => storage_path('app/public'),
        //     'url' => env('APP_URL').'/storage',
        //     'visibility' => 'public',
        //     'throw' => false,
        // ],

        'private' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_PRIVATE_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_PRIVATE_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'public' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_PUBLIC_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_PUBLIC_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'tmp' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_PRIVATE_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_PRIVATE_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'root' => 'tmp/',
        ],

        'discovery' => env('APP_ENV') !== 'production'
            ? [
                'driver' => 'local',
                'root' => storage_path('app/discovery'),
                'throw' => false,
            ]
            : [
                'driver' => 'sftp',
                'host' => env('DISCOVERY_FTP_HOST'),

                // Settings for basic authentication...
                'username' => env('DISCOVERY_FTP_USERNAME'),
                'password' => env('DISCOVERY_FTP_PASSWORD'),

                // Settings for SSH key based authentication with encryption password...
                'privateKey' => env('DISCOVERY_FTP_PRIVATE_KEY'),
                'passphrase' => env('DISCOVERY_FTP_PASSPHRASE'),

                // Settings for file / directory permissions...
                'visibility' => 'private', // `private` = 0600, `public` = 0644
                'directory_visibility' => 'private', // `private` = 0700, `public` = 0755

                // Optional SFTP CrmSetting...
                // 'hostFingerprint' => env('DISCOVERY_FTP_HOST_FINGERPRINT'),
                // 'maxTries' => 4,
                // 'passphrase' => env('DISCOVERY_FTP_PASSPHRASE'),
                // 'port' => env('DISCOVERY_FTP_PORT', 22),
                // 'root' => env('DISCOVERY_FTP_ROOT', ''),
                // 'timeout' => 30,
                // 'useAgent' => true,
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
        // public_path('storage') => storage_path('app/public'),
        // public_path('location-images') => storage_path('app/location-images'),
        // public_path('box-logos') => storage_path('app/box-logos'),
    ],

];
