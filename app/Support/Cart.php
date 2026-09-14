<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Orders\QuoteCart;
use Illuminate\Contracts\Session\Session;

/**
 * Session cart. Holds no prices — those are always re-quoted on the server. A line is a product plus
 * an optional cut/offal/packing choice; two adds of the same product+options merge into one line.
 */
final class Cart
{
    private const KEY = 'cart.lines';

    public function __construct(private readonly Session $session) {}

    /** @return array<int|string, int|array<string, int|null>> raw shape, straight from the session */
    public function lines(): array
    {
        $lines = $this->session->get(self::KEY, []);

        return is_array($lines) ? $lines : [];
    }

    public function add(int $productId, int $quantity, ?int $cutOptionId = null, ?int $offalOptionId = null, ?int $packingOptionId = null): void
    {
        $line = new CartLine($productId, $quantity, $cutOptionId, $offalOptionId, $packingOptionId);
        $lines = $this->lines();
        $key = $line->key();

        $existingQuantity = is_int($lines[$key] ?? null) ? $lines[$key] : ($lines[$key]['quantity'] ?? 0);
        $newQuantity = min(QuoteCart::MAX_QUANTITY, ((int) $existingQuantity) + $quantity);

        $lines[$key] = $line->cutOptionId === null && $line->offalOptionId === null && $line->packingOptionId === null
            ? $newQuantity
            : (new CartLine($productId, $newQuantity, $cutOptionId, $offalOptionId, $packingOptionId))->toArray();

        $this->session->put(self::KEY, $lines);
    }

    public function updateQuantity(string $lineKey, int $quantity): void
    {
        $lines = $this->lines();
        if (! array_key_exists($lineKey, $lines)) {
            return;
        }

        $quantity = min(QuoteCart::MAX_QUANTITY, max(1, $quantity));
        $lines[$lineKey] = is_int($lines[$lineKey]) ? $quantity : [...$lines[$lineKey], 'quantity' => $quantity];
        $this->session->put(self::KEY, $lines);
    }

    public function remove(string $lineKey): void
    {
        $lines = $this->lines();
        unset($lines[$lineKey]);
        $this->session->put(self::KEY, $lines);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    public function count(): int
    {
        $total = 0;
        foreach ($this->lines() as $key => $value) {
            $total += CartLine::fromRaw($key, $value)->quantity;
        }

        return $total;
    }
}
