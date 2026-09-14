<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop\Concerns;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Guests reach their order with the private token from checkout (URL or this browser's session);
 * signed-in customers reach their own orders. Everyone else gets 404, not 403, so order numbers
 * can't be probed (gap 01, IDOR).
 */
trait AuthorizesOrderAccess
{
    protected function authorizeOrderAccess(Request $request, Order $order): void
    {
        $user = $request->user();
        if ($user !== null && $order->user_id === $user->id) {
            return;
        }

        $token = $request->query('token');
        $sessionToken = $request->session()->get("order_tokens.{$order->number}");

        $allowed = (is_string($token) && $order->matchesPublicToken($token))
            || (is_string($sessionToken) && $order->matchesPublicToken($sessionToken));

        abort_unless($allowed, 404);
    }
}
