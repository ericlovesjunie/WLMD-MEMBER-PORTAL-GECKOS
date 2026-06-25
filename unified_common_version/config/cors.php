<?php

$tenantDomains = array_keys(require __DIR__ . '/tenants.php');

$allowedOrigins = array_map(function ($domain) {
    if (str_starts_with($domain, 'localhost') || str_starts_with($domain, '127.')) {
        return 'http://' . $domain;
    }
    return 'https://' . $domain;
}, $tenantDomains);

$allowedOrigins[] = 'http://localhost:5173';
$allowedOrigins[] = 'http://localhost:3000';

return [
    'paths'                    => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => array_unique($allowedOrigins),
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [],
    'max_age'                  => 0,
    'supports_credentials'     => true,
];
