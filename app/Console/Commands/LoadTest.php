<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LaunchGateItem;
use App\Models\Category;
use App\Models\LaunchGateEvidence;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Guideline ch. 6 Sprint 09: a load test at 10x normal traffic. Hammers the storefront's read paths
 * (home, catalog, product pages, cart, health check) with concurrent requests from Laravel's own HTTP
 * client — no new tool to install — and passes on the error rate, p95 latency and whether the target
 * throughput (10 × --normal-rps) was actually reached. Checkout's write path under concurrency is
 * `ops:oversell-drill`'s job, since it needs CSRF sessions and would create real orders.
 */
class LoadTest extends Command
{
    protected $signature = 'ops:load-test
        {base-url : e.g. https://staging.halalbrothers.com}
        {--requests=1000 : total requests}
        {--concurrency=50 : requests in flight at once}
        {--normal-rps=2 : your normal requests/second — the target is 10x this}
        {--max-p95-ms=1500}
        {--max-error-pct=1}
        {--path=* : paths to hit (default: home, categories, cart, /up, and a few real product/category pages)}
        {--force : allow targeting the production APP_URL}';

    protected $description = 'Runs a concurrent HTTP load test against the storefront and records the result';

    public function handle(): int
    {
        $baseUrl = rtrim((string) $this->argument('base-url'), '/');
        if (app()->isProduction() && parse_url($baseUrl, PHP_URL_HOST) === parse_url((string) config('app.url'), PHP_URL_HOST) && ! $this->option('force')) {
            $this->error('That is the live site. Load-test staging (sized like production), or pass --force during a planned window.');

            return self::FAILURE;
        }

        $paths = $this->paths();
        $total = max(1, (int) $this->option('requests'));
        $concurrency = max(1, (int) $this->option('concurrency'));

        $latencies = [];
        $failures = [];
        $sent = 0;
        $started = hrtime(true);

        while ($sent < $total) {
            $batch = min($concurrency, $total - $sent);
            $responses = Http::pool(function (Pool $pool) use ($batch, $sent, $paths, $baseUrl): void {
                for ($i = 0; $i < $batch; $i++) {
                    $pool->withOptions(['allow_redirects' => false])->timeout(30)->get($baseUrl.$paths[($sent + $i) % count($paths)]);
                }
            });
            foreach ($responses as $response) {
                if (! $response instanceof Response) {
                    $failures[] = 'connection: '.mb_strimwidth($response instanceof \Throwable ? $response->getMessage() : 'no response', 0, 120, '…');

                    continue;
                }
                $latencies[] = (int) round(($response->transferStats?->getTransferTime() ?? 0) * 1000);
                if ($response->status() >= 400) {
                    $failures[] = 'HTTP '.$response->status().' '.($response->effectiveUri()?->getPath() ?? '');
                }
            }
            $sent += $batch;
            $this->output->write("\r{$sent}/{$total}");
        }
        $this->newLine();

        $elapsedMs = max(1, intdiv(hrtime(true) - $started, 1_000_000));
        sort($latencies);
        $percentile = fn (int $p): int => $latencies === [] ? 0 : $latencies[min(count($latencies) - 1, (int) ceil($p / 100 * count($latencies)) - 1)];
        $achievedRps = intdiv($total * 1000, $elapsedMs);
        $targetRps = 10 * max(1, (int) $this->option('normal-rps'));
        $errorPctTimes100 = intdiv(count($failures) * 10_000, $total);

        $passed = $errorPctTimes100 <= (int) $this->option('max-error-pct') * 100
            && $percentile(95) <= (int) $this->option('max-p95-ms')
            && $achievedRps >= $targetRps;

        $evidence = sprintf(
            '%s: %d requests at concurrency %d → %d req/s (target %d = 10 × %d normal); p50 %d ms, p95 %d ms, max %d ms; errors %d (%s%%)',
            parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl, $total, $concurrency, $achievedRps, $targetRps, (int) $this->option('normal-rps'),
            $percentile(50), $percentile(95), $latencies === [] ? 0 : end($latencies), count($failures), number_format($errorPctTimes100 / 100, 2),
        );
        LaunchGateEvidence::record(LaunchGateItem::LoadTest, $passed, $evidence, $elapsedMs);

        foreach (array_slice(array_count_values($failures), 0, 10, true) as $failure => $count) {
            $this->line("<fg=red>✗</> {$count} × {$failure}");
        }
        $passed ? $this->info("PASS — {$evidence}") : $this->error("FAIL — {$evidence}");

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    private function paths(): array
    {
        /** @var list<string> $given */
        $given = (array) $this->option('path');
        if ($given !== []) {
            return array_values(array_map(fn (string $p): string => '/'.ltrim($p, '/'), $given));
        }

        return array_values([
            '/', '/categories', '/cart', '/up',
            ...Product::query()->active()->limit(5)->pluck('slug')->map(fn (string $slug): string => "/products/{$slug}")->all(),
            ...Category::query()->limit(3)->pluck('slug')->map(fn (string $slug): string => "/categories/{$slug}")->all(),
        ]);
    }
}
