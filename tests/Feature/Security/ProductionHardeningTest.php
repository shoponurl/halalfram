<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * S09 self-audit regression tests for findings that only show up outside the test environment.
 */

/** Laravel skips CSRF entirely under unit tests — which is exactly what hid SA-02. Enforce it here. */
final class EnforcedCsrfToken extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}

it('SA-02: lets signed carrier webhooks through CSRF, while ordinary forms still need a token', function () {
    app()->bind(ValidateCsrfToken::class, EnforcedCsrfToken::class);

    // Reaching the controller (400 = bad signature) proves CSRF didn't answer 419 first
    $this->post('/webhooks/twilio/sms', ['From' => '+12675550123', 'Body' => 'STOP'])->assertStatus(400);
    $this->post('/webhooks/easypost/tracking', ['result' => []])->assertStatus(400);
    $this->post('/stripe/webhook', [])->assertStatus(503);   // payments not configured in tests — still past CSRF

    $this->post('/checkout', [])->assertStatus(419);
    $this->post('/privacy/requests', [])->assertStatus(419);
});

it('SA-05: uses the real client IP behind a trusted proxy, and ignores forwarded headers otherwise', function () {
    Route::middleware('web')->get('/_test/client-ip', fn (Request $request) => $request->ip());

    $forwarded = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'];

    $this->withServerVariables($forwarded)->get('/_test/client-ip')->assertSeeText('10.0.0.5');

    config(['trustedproxy.proxies' => '10.0.0.5']);
    $this->withServerVariables($forwarded)->get('/_test/client-ip')->assertSeeText('203.0.113.9');
});

it('SA-06: sends anti-clickjacking, no-sniff and referrer headers on the storefront and the staff panel', function () {
    foreach (['/', '/admin/login'] as $path) {
        $this->get($path)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
});

it('SA-06: adds HSTS only over HTTPS', function () {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('SA-07: keeps the Slack log channel at critical regardless of the general log level', function () {
    $source = (string) file_get_contents(config_path('logging.php'));

    expect(preg_match("/'slack' => \[.*?'level' => env\('LOG_SLACK_LEVEL', 'critical'\)/s", $source))->toBe(1);
});

it('reports the database in the /up health check', function () {
    $this->get('/up')->assertOk();
});
