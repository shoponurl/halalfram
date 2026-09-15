<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Owner decision (guideline ch. 7, S07): own-farm animals have no purchase invoice, so their cost is
 * always a manual estimate — App\Models\Animal::isCostEstimated() flags that in every report so an
 * estimate is never shown or blended in as if it were a real, invoiced cost.
 */
enum AnimalCostSource: string
{
    case Purchased = 'purchased';
    case OwnFarm = 'own_farm';

    public function label(): string
    {
        return match ($this) {
            self::Purchased => 'Purchased',
            self::OwnFarm => 'Own farm',
        };
    }
}
