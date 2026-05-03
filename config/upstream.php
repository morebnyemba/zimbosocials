<?php

/**
 * DEPRECATED — This file is superseded by the `upstream_providers` database table,
 * managed via Admin → Upstream Providers. All provider configuration (URL, API key,
 * priority, status) now lives in the DB.
 *
 * These env vars are no longer read by the application code. They are kept here
 * only for backward-compatibility until old deployments are migrated.
 */

return [
    'enabled' => env('SMM_PROVIDER_ENABLED', true),
    'url'     => env('SMM_PROVIDER_URL'),
    'key'     => env('SMM_PROVIDER_KEY'),
    'timeout' => (int) env('SMM_PROVIDER_TIMEOUT', 20),
];
