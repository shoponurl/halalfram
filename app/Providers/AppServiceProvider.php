<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\User;
use App\Payments\NullTaxCalculator;
use App\Payments\PaymentGateway;
use App\Payments\PayPalGateway;
use App\Payments\StripeGateway;
use App\Payments\TaxCalculator;
use App\Shipping\EasyPostGateway;
use App\Shipping\ShippingGateway;
use App\Sms\SmsGateway;
use App\Sms\TwilioSmsGateway;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, fn () => new StripeGateway(
            secretKey: config('services.stripe.secret'),
            publishableKey: config('services.stripe.key'),
            webhookSecret: config('services.stripe.webhook_secret'),
            currency: (string) config('catchweight.currency'),
        ));

        $this->app->singleton(PayPalGateway::class, fn () => new PayPalGateway(
            clientId: config('services.paypal.client_id'),
            clientSecret: config('services.paypal.client_secret'),
            mode: (string) config('services.paypal.mode'),
            currency: (string) config('catchweight.currency'),
        ));

        $this->app->singleton(TaxCalculator::class, NullTaxCalculator::class);

        $this->app->singleton(SmsGateway::class, fn () => new TwilioSmsGateway(
            accountSid: config('services.twilio.sid'),
            authToken: config('services.twilio.token'),
            messagingServiceSid: config('services.twilio.messaging_service_sid'),
            fromNumber: config('services.twilio.from'),
        ));

        $this->app->singleton(ShippingGateway::class, fn () => new EasyPostGateway(
            apiKey: config('services.easypost.api_key'),
            webhookSecret: config('services.easypost.webhook_secret'),
            fromName: (string) config('catchweight.shop_address.name'),
            fromAddress1: (string) config('catchweight.shop_address.address1'),
            fromCity: (string) config('catchweight.shop_address.city'),
            fromState: (string) config('catchweight.shop_address.state'),
            fromZip: (string) config('catchweight.shop_address.zip'),
        ));
    }

    public function boot(): void
    {
        $production = $this->app->isProduction();

        // Guardrails (CLAUDE.md): fail loudly on the mistakes that corrupt money, weights or data
        Model::preventLazyLoading(! $production);                // N+1 queries break during Eid traffic
        Model::preventAccessingMissingAttributes(! $production);
        Model::preventSilentlyDiscardingAttributes();            // a non-fillable price/role must never vanish silently
        DB::prohibitDestructiveCommands($production);            // no migrate:fresh / db:wipe on production

        if ($production) {
            URL::forceHttps();
        }

        Password::defaults(fn () => $production
            ? Password::min(12)->mixedCase()->numbers()->uncompromised()
            : Password::min(12));

        // Checkout creates a Stripe customer + PaymentIntent per attempt: keep bots from card-testing (gap 11)
        RateLimiter::for('checkout', fn (Request $request) => [
            Limit::perMinute(5)->by('checkout:'.$request->ip()),
            Limit::perHour(30)->by('checkout-hour:'.$request->ip()),
        ]);

        // S09 self-audit SA-01: each link request sends an email — keep it from being used to spam an inbox.
        RateLimiter::for('store-credit', fn (Request $request) => [
            Limit::perMinute(3)->by('store-credit:'.$request->ip()),
            Limit::perHour(5)->by('store-credit-email:'.strtolower(trim((string) $request->input('credit_email')))),
        ]);

        // Guideline ch. 8 launch gate (monitoring): /up is what the external uptime monitor polls. By
        // default it only proves PHP boots — make a dead database fail it too (the query throws → 500).
        Event::listen(DiagnosingHealth::class, fn () => DB::select('select 1'));

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->last_login_at = now();
                $event->user->saveQuietly();
                AuditLog::record('staff.login', "{$event->user->email} logged in", $event->user, [], $event->user);
            }
        });
    }
}
