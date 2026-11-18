<?php

return [

    'shipfast' => [

        /*
        |--------------------------------------------------------------------------
        | ShipFast Provider Configuration
        |--------------------------------------------------------------------------
        |
        | The base URI is set per environment: staging points at the sandbox,
        | production at the live API. Changing this value changes the destination
        | of every shipment request — no code change needed.
        |
        */

        'base_uri' => env('SHIPFAST_BASE_URI', 'https://sandbox.shipfast.example'),

        'key' => env('SHIPFAST_KEY', 'test'),

    ],

];
