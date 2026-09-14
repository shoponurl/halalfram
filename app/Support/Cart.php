<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Orders\QuoteCart;
use Illuminate\Contracts\Session\Session;

/** Session cart: product id => quantity. Holds no prices — those are always re-quoted on the server. */
final class Cart
{
    private const KEY = 'cart.lines';

    public function __construct(private readonly Session $session) {}

    /** @return array<int, int> */
    public function lines(): array
    {
        $lines = $this->session->get(self::KEY, []);

        return is_array($lines) ? array_map(intval(...), $lines) : [];
    }

    public function add(int $productId, int $quantity): void
    {
        $lines = $this->lines();
        $lines[$productId] = min(QuoteCart::MAX_QUANTITY, ($lines[$productId] ?? 0) + $quantity);
        $this->session->put(self::KEY, $lines);
    }

    public function set(int $productId, int $quantity): void
    {
        $lines = $this->lines();
        $lines[$productId] = min(QuoteCart::MAX_QUANTITY, max(1, $quantity));
        $this->session->put(self::KEY, $lines);
    }

    public function remove(int $productId): void
    {
        $lines = $this->lines();
        unset($lines[$productId]);
        $this->session->put(self::KEY, $lines);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    public function count(): int
    {
        return array_sum($this->lines());
    }
}
