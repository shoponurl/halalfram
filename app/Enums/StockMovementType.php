<?php

declare(strict_types=1);

namespace App\Enums;

/** Append-only ledger of everything that happens to a lot's stock (rule 04, guideline ch. 6 Sprint 03). */
enum StockMovementType: string
{
    case Received = 'received';       // stock enters the lot (butchering/receiving)
    case Reserved = 'reserved';       // an order placed a hold on stock (S03: held for the full order lifecycle)
    case Released = 'released';       // a reservation gave stock back (order failed/expired/cancelled)
    case Consumed = 'consumed';       // an order's actual weight was deducted at finalization
    case Wastage = 'wastage';         // spoilage/loss not tied to any order

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Reserved => 'Reserved',
            self::Released => 'Released',
            self::Consumed => 'Consumed',
            self::Wastage => 'Wastage',
        };
    }
}
