<?php

use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$container = getenv('BETA_TEST_CONTAINER');
$project = getenv('BETA_TEST_PROJECT');
$password = getenv('BETA_TEST_DB_PASSWORD');
$port = getenv('BETA_TEST_DB_PORT');

if (! is_string($container) || ! preg_match('/^[a-f0-9]{64}$/', $container) ||
    ! is_string($project) || ! preg_match('/^bl-otp-test-[a-f0-9]{12}$/', $project) ||
    ! is_string($password) || ! preg_match('/^[a-f0-9]{48}$/', $password) ||
    ! is_string($port) || ! ctype_digit($port) || (int) $port < 1024 || (int) $port > 65535) {
    throw new RuntimeException('Use scripts/test-mariadb.php to provision an isolated test container.');
}

$inspect = new Process([
    'docker', 'inspect', '--format',
    '{{json .Config.Labels}}{{println}}{{json .NetworkSettings.Ports}}{{println}}{{json .Mounts}}{{println}}{{json .HostConfig.Tmpfs}}',
    $container,
]);
$inspect->mustRun();
$metadata = explode("\n", trim($inspect->getOutput()));

if (count($metadata) !== 4) {
    throw new RuntimeException('Cannot verify test container isolation.');
}

$labels = json_decode($metadata[0], true, flags: JSON_THROW_ON_ERROR);
$ports = json_decode($metadata[1], true, flags: JSON_THROW_ON_ERROR);
$mounts = json_decode($metadata[2], true, flags: JSON_THROW_ON_ERROR);
$temporaryFilesystems = json_decode($metadata[3], true, flags: JSON_THROW_ON_ERROR);
$temporaryDatabase = array_key_exists('/var/lib/mysql', $temporaryFilesystems);

foreach ($mounts as $mount) {
    if ($mount['Destination'] === '/var/lib/mysql' && $mount['Type'] !== 'tmpfs') {
        $temporaryDatabase = false;
    }
}

if (($labels['com.docker.compose.project'] ?? null) !== $project ||
    ($labels['com.docker.compose.service'] ?? null) !== 'mariadb' ||
    ($labels['bl.isolated-otp-tests'] ?? null) !== '1' ||
    ($ports['3306/tcp'] ?? null) !== [['HostIp' => '127.0.0.1', 'HostPort' => $port]] ||
    ! $temporaryDatabase) {
    throw new RuntimeException('Refusing a database without verified disposable-container isolation.');
}

foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)),
    'APP_DEBUG' => 'false',
    'DB_CONNECTION' => 'mariadb',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => $port,
    'DB_DATABASE' => 'beta_otp_test',
    'DB_USERNAME' => 'beta_otp_test',
    'DB_PASSWORD' => $password,
    'DB_URL' => '',
    'DB_SOCKET' => '',
    'DB_CACHE_CONNECTION' => 'mariadb',
    'DB_CACHE_LOCK_CONNECTION' => 'mariadb',
    'DB_CACHE_TABLE' => 'cache',
    'DB_CACHE_LOCK_TABLE' => 'cache_locks',
    'CACHE_STORE' => 'database',
    'CACHE_LIMITER' => 'database',
    'CACHE_PREFIX' => $project,
    'BCRYPT_ROUNDS' => '4',
    'SMS_DRIVER' => 'log',
    'SMS_LOG_OTP_IN_NON_PRODUCTION' => 'false',
    'ESCORT_PORTAL_DRIVER' => 'fixture',
    'ESCORT_PORTAL_FIXTURE_DIRECTORY' => 'tests/Fixtures/escort_ads/active',
    'ESCORT_PORTAL_DEVELOPMENT_PHONE_OVERRIDE' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
] as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$application = require dirname(__DIR__).'/bootstrap/app.php';

if ($application->configurationIsCached()) {
    throw new RuntimeException('Refusing MariaDB tests with cached application configuration.');
}

unset($application);
