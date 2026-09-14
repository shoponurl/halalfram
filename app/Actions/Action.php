<?php

declare(strict_types=1);

namespace App\Actions;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Base for domain actions (checkout, capture payment, record weight, book a share…).
 *
 * Conventions (see CLAUDE.md):
 *  - One public `handle()` per action, typed input, no Request objects — controllers pass validated data.
 *  - Authorization happens in the controller/Filament page via a Policy BEFORE the action runs.
 *  - Anything touching stock, shares or money runs inside `transaction()` and locks rows with lockForUpdate().
 *  - External calls (Stripe, Twilio, carriers) are dispatched to the queue, never made inline.
 */
abstract class Action
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @param  positive-int  $attempts
     * @return T
     */
    protected function transaction(Closure $callback, int $attempts = 3): mixed
    {
        return DB::transaction($callback, $attempts);
    }
}
