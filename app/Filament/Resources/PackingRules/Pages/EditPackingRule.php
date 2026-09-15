<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackingRules\Pages;

use App\Filament\Resources\PackingRules\PackingRuleResource;
use Filament\Resources\Pages\EditRecord;

class EditPackingRule extends EditRecord
{
    protected static string $resource = PackingRuleResource::class;
}
