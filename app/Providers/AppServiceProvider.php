<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->last_login_at = now();
                $event->user->saveQuietly();
            }
        });
    }
}
