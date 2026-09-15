<?php

declare(strict_types=1);

namespace App\Payments\Exceptions;

use RuntimeException;

/** Thrown by a gateway for a capability it genuinely can't do (e.g. PayPal off-session recharge without Vault approval). */
final class PaymentMethodNotSupported extends RuntimeException {}
