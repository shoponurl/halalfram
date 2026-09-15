<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Carriers can't send a CSRF token; each webhook is authenticated by its signature instead (gap 04).
        // S09 self-audit SA-02: Twilio's and EasyPost's were missing here, so production would have
        // answered every STOP message and tracking update with a 419 (tests skip CSRF, which hid it).
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'webhooks/twilio/sms',
            'webhooks/easypost/tracking',
        ]);

        // S09 self-audit SA-06 (the staff panel adds the same middleware in AdminPanelProvider). Trusted
        // proxies (SA-05) need no code here: TrustProxies reads config/trustedproxy.php.
        $middleware->web(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
