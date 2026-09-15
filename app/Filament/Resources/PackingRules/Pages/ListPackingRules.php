<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackingRules\Pages;

use App\Filament\Resources\PackingRules\PackingRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPackingRules extends ListRecords
{
    protected static string $resource = PackingRuleResource::class;

    /** @return array<CreateAction> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
