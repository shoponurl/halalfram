<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One normalized cart line: a product plus its (optional) cut/offal/packing choice. Sprint 01 carts
 * with no options are represented the same way, with all three option ids null.
 */
final class CartLine
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly ?int $cutOptionId = null,
        public readonly ?int $offalOptionId = null,
        public readonly ?int $packingOptionId = null,
    ) {}

    /** Stable identity for a line: two adds with the same product+options merge into one line. */
    public function key(): string
    {
        if ($this->cutOptionId === null && $this->offalOptionId === null && $this->packingOptionId === null) {
            return (string) $this->productId;
        }

        return sprintf('%d-%s-%s-%s', $this->productId, $this->cutOptionId ?? '0', $this->offalOptionId ?? '0', $this->packingOptionId ?? '0');
    }

    /** @return array{product_id: int, quantity: int, cut_option_id: int|null, offal_option_id: int|null, packing_option_id: int|null} */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'cut_option_id' => $this->cutOptionId,
            'offal_option_id' => $this->offalOptionId,
            'packing_option_id' => $this->packingOptionId,
        ];
    }

    /**
     * Reads one raw session/request entry back into a line. Sprint 01 shape ($key = product id,
     * $value = plain int quantity) and the Sprint 02 shape ($value = the array above) are both accepted.
     */
    public static function fromRaw(int|string $key, mixed $value): self
    {
        if (is_int($value)) {
            return new self((int) $key, $value);
        }

        if (is_array($value)) {
            return new self(
                (int) ($value['product_id'] ?? 0),
                (int) ($value['quantity'] ?? 0),
                isset($value['cut_option_id']) ? (int) $value['cut_option_id'] : null,
                isset($value['offal_option_id']) ? (int) $value['offal_option_id'] : null,
                isset($value['packing_option_id']) ? (int) $value['packing_option_id'] : null,
            );
        }

        return new self((int) $key, 0);
    }
}
