<?php

require dirname(__DIR__).'/vendor/autoload.php';

foreach ([
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
] as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$application = require dirname(__DIR__).'/bootstrap/app.php';

if ($application->configurationIsCached()) {
    throw new RuntimeException('Refusing to run tests with cached application configuration. Use an uncached, isolated test environment.');
}

unset($application);
