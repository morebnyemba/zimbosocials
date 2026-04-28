<?php

return [
    'enabled' => env('SMM_PROVIDER_ENABLED', true),
    'url' => env('SMM_PROVIDER_URL'),
    'key' => env('SMM_PROVIDER_KEY'),
    'timeout' => (int) env('SMM_PROVIDER_TIMEOUT', 20),
];
