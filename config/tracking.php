<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tracking provider
    |--------------------------------------------------------------------------
    |
    | Where status and date updates come from. "manual" means they are only
    | entered through the API / UI. "fake" is an in-memory provider used by
    | the test-suite and by local demos. A real adapter (Shipmondo for the
    | last mile, a container-tracking API for the sea leg) plugs in here.
    |
    */

    'provider' => env('TRACKING_PROVIDER', 'manual'),

];
