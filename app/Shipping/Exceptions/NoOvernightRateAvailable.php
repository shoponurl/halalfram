<?php

declare(strict_types=1);

namespace App\Shipping\Exceptions;

use RuntimeException;

final class NoOvernightRateAvailable extends RuntimeException {}
