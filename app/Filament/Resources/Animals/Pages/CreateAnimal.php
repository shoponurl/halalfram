<?php

declare(strict_types=1);

namespace App\Filament\Resources\Animals\Pages;

use App\Filament\Resources\Animals\AnimalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnimal extends CreateRecord
{
    protected static string $resource = AnimalResource::class;

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed> */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = auth()->id();

        return $data;
    }
}
