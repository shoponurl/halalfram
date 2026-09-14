<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lots\Pages;

use App\Actions\Inventory\ReceiveStock;
use App\Enums\StorageLocation;
use App\Filament\Resources\Lots\LotResource;
use App\Models\Animal;
use App\Models\Product;
use App\Models\User;
use App\Support\Weight;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CreateLot extends CreateRecord
{
    protected static string $resource = LotResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(ReceiveStock::class)->handle(
            product: Product::query()->findOrFail((int) $data['product_id']),
            animal: filled($data['animal_id'] ?? null) ? Animal::query()->find((int) $data['animal_id']) : null,
            storageLocation: StorageLocation::from($data['storage_location']),
            packDate: Carbon::parse($data['pack_date']),
            useByDate: Carbon::parse($data['use_by_date']),
            weight: Weight::pounds((string) $data['weight']),
            receivedBy: $user,
        );
    }
}
