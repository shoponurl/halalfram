<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Action;
use App\Mail\StoreCreditLinkMail;
use App\Models\StoreCreditAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Store credit is tied to an email address, so using it online requires proving that address is
 * yours (S09 self-audit, finding SA-01): the balance is only shown, and only spendable, in a browser
 * session that followed a link sent to that inbox. Callers always get the same response whether or
 * not the address holds any credit, so the form can't be used to enumerate balances.
 */
final class SendStoreCreditLink extends Action
{
    public const LINK_MINUTES = 30;

    public function handle(string $email): void
    {
        $email = strtolower(trim($email));

        $account = StoreCreditAccount::query()->find($email);
        if ($account === null || $account->balance_cents <= 0) {
            return;
        }

        $token = Str::random(48);
        Cache::put(self::cacheKey($token), $email, now()->addMinutes(self::LINK_MINUTES));

        Mail::to($email)->queue(new StoreCreditLinkMail(route('store-credit.confirm', $token)));
    }

    /** The verified email for a link token, or null if it's unknown or expired. */
    public static function emailForToken(string $token): ?string
    {
        $email = Cache::get(self::cacheKey($token));

        return is_string($email) ? $email : null;
    }

    private static function cacheKey(string $token): string
    {
        return 'store-credit-link:'.hash('sha256', $token);
    }
}
