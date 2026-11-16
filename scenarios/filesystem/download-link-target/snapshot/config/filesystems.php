<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'serve' => true,
            'throw' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Private Contracts Disk
        |--------------------------------------------------------------------------
        |
        | Signed customer contracts are confidential: this disk is deliberately
        | NOT publicly served in any environment. Downloads go through the
        | application's authorized streaming route.
        |
        */

        'contracts' => [
            'driver' => 'local',
            'root' => storage_path('app/contracts'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

    ],

];
