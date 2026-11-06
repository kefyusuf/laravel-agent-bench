<?php

return [

    'redis' => [

        /*
        |--------------------------------------------------------------------------
        | Flash-Sale Redis
        |--------------------------------------------------------------------------
        |
        | The reservation race runs against a single Redis instance: host 'redis'
        | in the compose environment, a published port on native/CI runs.
        |
        */

        'race_host' => env('REDIS_RACE_HOST', 'redis:6379'),

    ],

];
