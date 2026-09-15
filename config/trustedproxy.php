<?php

declare(strict_types=1);

// S09 self-audit SA-05: behind a load balancer every customer would otherwise share the proxy's IP —
// one rate-limit bucket (5 checkouts/min) for the whole site, and Twilio's signature checked against
// the wrong URL scheme.
return [
    // Read by Illuminate\Http\Middleware\TrustProxies. Comma-separated IPs/CIDRs of the load balancer
    // or CDN in front of the app, or "*" to trust the directly connecting proxy. Leave empty when PHP
    // receives traffic directly (e.g. plain Forge nginx with no load balancer).
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
