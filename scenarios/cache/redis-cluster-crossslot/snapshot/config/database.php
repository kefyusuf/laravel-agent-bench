<?php

return [

    'redis' => [

        /*
        |--------------------------------------------------------------------------
        | Redis Cluster Seeds
        |--------------------------------------------------------------------------
        |
        | Production runs a 3-master Redis cluster; staging may point this at a
        | single node. The phpredis RedisCluster client connects to any seed
        | node and discovers the rest of the topology.
        |
        */

        'seeds' => env('REDIS_SEEDS', 'redis-1:6379,redis-2:6379,redis-3:6379'),

    ],

];
