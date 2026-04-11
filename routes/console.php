<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('scraper:probe-proxy {--url=} {--attempts=} {--method=GET}', function () {
    /** @var \App\Scraper\Proxy\ProxyProbeService $probeService */
    $probeService = app(\App\Scraper\Proxy\ProxyProbeService::class);

    if (! $probeService->isConfigured()) {
        $this->error('The rotating proxy endpoint is not configured.');

        return self::FAILURE;
    }

    $url = $this->option('url') ?: config('scraping.probe.url');
    $attempts = max(1, (int) ($this->option('attempts') ?: config('scraping.probe.attempts_per_run', 5)));
    $method = strtoupper((string) $this->option('method'));
    $summary = [];

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $probe = $probeService->probe($url, $method);
        $summary[$probe->outcome] = ($summary[$probe->outcome] ?? 0) + 1;

        $this->line(sprintf(
            '#%d outcome=%s status=%s latency_ms=%s target=%s',
            $attempt,
            $probe->outcome,
            $probe->http_status ?? '-',
            $probe->latency_ms ?? '-',
            $probe->target_host,
        ));
    }

    $this->table(
        ['Outcome', 'Count'],
        collect($summary)
            ->map(fn (int $count, string $outcome): array => [$outcome, $count])
            ->values()
            ->all(),
    );

    return self::SUCCESS;
})->purpose('Probe the rotating scraper proxy and record attempt telemetry.');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('scraping.probe.enabled', false)) {
    Schedule::command(sprintf(
        'scraper:probe-proxy --attempts=%d',
        max(1, (int) config('scraping.probe.attempts_per_run', 5)),
    ))->everyFifteenMinutes();
}
