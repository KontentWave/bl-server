<?php

return [
    'proxy' => [
        'driver' => env('SCRAPER_PROXY_DRIVER', 'none'),
        'scheme' => env('SCRAPER_PROXY_SCHEME', 'http'),
        'host' => env('SCRAPER_PROXY_HOST'),
        'port' => (int) env('SCRAPER_PROXY_PORT', 80),
        'username' => env('SCRAPER_PROXY_USERNAME'),
        'password' => env('SCRAPER_PROXY_PASSWORD'),
        'connect_timeout' => (int) env('SCRAPER_PROXY_CONNECT_TIMEOUT', 10),
        'timeout' => (int) env('SCRAPER_PROXY_TIMEOUT', 10),
    ],
    'probe' => [
        'enabled' => (bool) env('SCRAPER_PROXY_PROBE_ENABLED', false),
        'url' => env('SCRAPER_PROXY_PROBE_URL', 'https://amaterky.sk/'),
        'attempts_per_run' => (int) env('SCRAPER_PROXY_PROBE_ATTEMPTS', 5),
        'capture_body_preview' => (bool) env('SCRAPER_PROXY_CAPTURE_BODY_PREVIEW', false),
    ],
];
