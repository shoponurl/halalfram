<?php

declare(strict_types=1);

namespace App\Actions\Launch;

use App\Actions\Action;
use App\Enums\LaunchGateItem;
use App\Enums\Role;
use App\Models\LaunchGateEvidence;
use App\Models\User;
use App\Shipping\ShippingGateway;
use App\Support\SoftLaunch;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Guideline ch. 8: "the gate says DONE / NOT DONE with proof — never 'we're ready'". Each item is
 * judged live (automated), from the latest recorded drill (must be a recent pass, in this
 * environment), or from the latest manual sign-off.
 */
final class EvaluateLaunchGate extends Action
{
    public function __construct(
        private readonly ReconcileLedgers $reconcile,
        private readonly ShippingGateway $shipping,
    ) {}

    /** @return list<array{item: LaunchGateItem, done: bool, evidence: string}> */
    public function handle(): array
    {
        return array_map(fn (LaunchGateItem $item): array => ['item' => $item, ...$this->evaluate($item)], LaunchGateItem::cases());
    }

    /** @return array{done: bool, evidence: string} */
    private function evaluate(LaunchGateItem $item): array
    {
        if ($item === LaunchGateItem::ColdChainShipment && ! ($this->shipping->isConfigured() && SoftLaunch::allowsShipping())) {
            return ['done' => true, 'evidence' => 'Not needed for this launch: nationwide shipping is switched off.'];
        }

        return match ($item->kind()) {
            'automated' => $this->automated($item),
            'drill' => $this->fromEvidence($item, maxAgeDays: (int) config('launch.drill_max_age_days')),
            default => $this->fromEvidence($item, maxAgeDays: null),
        };
    }

    /** @return array{done: bool, evidence: string} */
    private function automated(LaunchGateItem $item): array
    {
        return match ($item) {
            LaunchGateItem::DebugOff => self::result(
                config('app.env') === 'production' && config('app.debug') === false,
                'APP_ENV='.config('app.env').', APP_DEBUG='.(config('app.debug') ? 'true' : 'false'),
            ),
            LaunchGateItem::HttpsOnly => self::result(
                str_starts_with((string) config('app.url'), 'https://') && config('session.secure') === true,
                'APP_URL='.config('app.url').', SESSION_SECURE_COOKIE='.json_encode(config('session.secure')),
            ),
            LaunchGateItem::StaffTwoFactor => $this->staffTwoFactor(),
            LaunchGateItem::NoDevAccounts => $this->noDevAccounts(),
            LaunchGateItem::DatabaseLeastPrivilege => $this->databaseLeastPrivilege(),
            LaunchGateItem::LivePayments => self::result(
                str_starts_with((string) config('services.stripe.secret'), 'sk_live_')
                    && str_starts_with((string) config('services.stripe.key'), 'pk_live_')
                    && (string) config('services.stripe.webhook_secret') !== '',
                'Stripe secret key: '.self::keyMode((string) config('services.stripe.secret')).', publishable key: '.self::keyMode((string) config('services.stripe.key')).', webhook secret: '.((string) config('services.stripe.webhook_secret') !== '' ? 'set' : 'missing'),
            ),
            LaunchGateItem::QueueAsync => self::result(config('queue.default') !== 'sync', 'QUEUE_CONNECTION='.config('queue.default')),
            LaunchGateItem::MailConfigured => self::result(
                config('mail.default') === 'postmark' && (string) config('services.postmark.token') !== '',
                'MAIL_MAILER='.config('mail.default').', Postmark token: '.((string) config('services.postmark.token') !== '' ? 'set' : 'missing'),
            ),
            LaunchGateItem::AlertsConfigured => $this->alertsConfigured(),
            LaunchGateItem::Reconciliation => $this->reconciliation(),
            default => self::result(false, 'No automated check defined.'),
        };
    }

    /** @return array{done: bool, evidence: string} */
    private function staffTwoFactor(): array
    {
        $missing = User::query()->where('is_active', true)->role(Role::values())->whereNull('app_authentication_secret')->pluck('email');

        return self::result($missing->isEmpty(), $missing->isEmpty() ? 'Every active staff account has an authenticator app set up.' : 'No 2FA yet: '.$missing->implode(', '));
    }

    /** @return array{done: bool, evidence: string} */
    private function noDevAccounts(): array
    {
        $dev = User::query()->where('is_active', true)
            ->where(fn ($q) => $q->where('email', 'like', '%@halalbrothers.test')->orWhere('email', 'like', '%@example.com'))
            ->pluck('email');

        return self::result($dev->isEmpty(), $dev->isEmpty() ? 'No active demo (@halalbrothers.test) or placeholder accounts.' : 'Deactivate: '.$dev->implode(', '));
    }

    /** @return array{done: bool, evidence: string} */
    private function databaseLeastPrivilege(): array
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return self::result(false, 'Only checkable on MySQL/MariaDB.');
        }

        try {
            $grants = array_map(fn (object $row): string => (string) array_values((array) $row)[0], DB::select('SHOW GRANTS FOR CURRENT_USER()'));
        } catch (Throwable $e) {
            return self::result(false, 'Could not read grants: '.$e->getMessage());
        }

        $dangerous = array_values(array_filter($grants, fn (string $grant): bool => (bool) preg_match('/\b(ALL PRIVILEGES|DROP|ALTER)\b/i', $grant)));

        return self::result(
            $dangerous === [],
            $dangerous === [] ? 'Grants: '.implode(' | ', $grants) : 'Too broad: '.implode(' | ', $dangerous).' — apply deploy/mysql-production-grants.sql',
        );
    }

    /** @return array{done: bool, evidence: string} */
    private function alertsConfigured(): array
    {
        /** @var list<string> $stack */
        $stack = (array) config('logging.channels.stack.channels', []);
        $inStack = config('logging.default') === 'stack' && in_array('alerts', $stack, true);
        $destinations = array_filter([
            (string) config('logging.channels.slack.url') !== '' ? 'Slack webhook' : null,
            (string) config('launch.alerts.email') !== '' ? 'email' : null,
        ]);

        return self::result(
            $inStack && $destinations !== [],
            'LOG_STACK='.implode(',', $stack).'; destinations: '.($destinations === [] ? 'none' : implode(' + ', $destinations)),
        );
    }

    /** @return array{done: bool, evidence: string} */
    private function reconciliation(): array
    {
        $mismatches = $this->reconcile->handle();

        return self::result($mismatches === [], $mismatches === [] ? 'Every order, lot, store-credit and weight ledger reconciles.' : count($mismatches).' mismatch(es), first: '.$mismatches[0]);
    }

    /** @return array{done: bool, evidence: string} */
    private function fromEvidence(LaunchGateItem $item, ?int $maxAgeDays): array
    {
        $latest = LaunchGateEvidence::query()
            ->where('item', $item)
            ->where('environment', (string) config('app.env'))
            ->orderByDesc('recorded_at')->orderByDesc('id')
            ->first();

        if ($latest === null) {
            return self::result(false, $item->kind() === 'drill' ? 'No drill recorded yet.' : 'Not signed off yet (php artisan launch:attest '.$item->value.').');
        }

        $when = $latest->recorded_at->toDateString();
        if (! $latest->passed) {
            return self::result(false, "Last result FAILED on {$when}: {$latest->evidence}");
        }
        if ($maxAgeDays !== null && $latest->recorded_at->lt(now()->subDays($maxAgeDays))) {
            return self::result(false, "Last pass is older than {$maxAgeDays} days ({$when}) — run it again.");
        }

        return self::result(true, "{$when}: {$latest->evidence}");
    }

    private static function keyMode(string $key): string
    {
        return match (true) {
            $key === '' => 'missing',
            str_contains($key, '_live_') => 'live',
            str_contains($key, '_test_') => 'TEST',
            default => 'unrecognized',
        };
    }

    /** @return array{done: bool, evidence: string} */
    private static function result(bool $done, string $evidence): array
    {
        return ['done' => $done, 'evidence' => $evidence];
    }
}
