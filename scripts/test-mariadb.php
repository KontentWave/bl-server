<?php

use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);

if (count($argv) !== 1 && (count($argv) !== 3 || $argv[1] !== '--filter' || $argv[2] === '')) {
    throw new RuntimeException('Usage: php scripts/test-mariadb.php [--filter test_name]');
}

if (! in_array('mysql', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('MariaDB tests require PHP pdo_mysql.');
}

$dockerHost = getenv('DOCKER_HOST');
$context = new Process(['docker', 'context', 'inspect', '--format', '{{.Endpoints.docker.Host}}'], $root);
$context->mustRun();

if (($dockerHost !== false && ! str_starts_with($dockerHost, 'unix://')) ||
    ! str_starts_with(trim($context->getOutput()), 'unix://')) {
    throw new RuntimeException('MariaDB tests require a local Unix-socket Docker daemon.');
}

foreach (['REPEATABLE-READ', 'READ-COMMITTED'] as $isolation) {
    $project = 'bl-otp-test-'.bin2hex(random_bytes(6));
    $environment = [
        'BETA_TEST_DB_PASSWORD' => bin2hex(random_bytes(24)),
        'BETA_TEST_ISOLATION' => $isolation,
        'BETA_TEST_PROJECT' => $project,
    ];
    $compose = ['docker', 'compose', '--project-name', $project, '--file', $root.'/compose.mariadb-test.yaml'];

    try {
        $start = new Process([...$compose, 'up', '--detach', '--wait', '--wait-timeout', '120'], $root, $environment);
        $start->setTimeout(180);
        $start->mustRun();

        $container = new Process([...$compose, 'ps', '--quiet', 'mariadb'], $root, $environment);
        $container->mustRun();
        $environment['BETA_TEST_CONTAINER'] = trim($container->getOutput());

        $port = new Process([...$compose, 'port', 'mariadb', '3306'], $root, $environment);
        $port->mustRun();

        if (! preg_match('/^127\.0\.0\.1:(\d+)$/', trim($port->getOutput()), $matches)) {
            throw new RuntimeException('The test database must be published only on loopback.');
        }

        $environment['BETA_TEST_DB_PORT'] = $matches[1];
        echo "Isolated MariaDB 11.4: {$isolation}\n";

        $tests = new Process([
            PHP_BINARY, $root.'/vendor/bin/phpunit', '--configuration', $root.'/phpunit.mariadb.xml', '--no-progress',
            ...array_slice($argv, 1),
        ], $root, $environment);
        $tests->setTimeout(180);
        $exitCode = $tests->run(function (string $type, string $output): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
        });

        if ($exitCode !== 0) {
            throw new RuntimeException("MariaDB tests failed under {$isolation} (exit {$exitCode}).");
        }
    } finally {
        $stop = new Process([...$compose, 'down', '--timeout', '10'], $root, $environment);
        $stop->setTimeout(60);
        $stop->mustRun();
    }
}
