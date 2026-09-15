<?php

declare(strict_types=1);

namespace App\Filament\Resources\PrivacyRequests;

use App\Filament\Resources\PrivacyRequests\Pages\ListPrivacyRequests;
use App\Filament\Resources\PrivacyRequests\Tables\PrivacyRequestsTable;
use App\Models\PrivacyRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PrivacyRequestResource extends Resource
{
    protected static ?string $model = PrivacyRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Privacy requests';

    protected static string|UnitEnum|null $navigationGroup = 'Compliance';

    protected static ?string $recordTitleAttribute = 'customer_email';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return PrivacyRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrivacyRequests::route('/'),
        ];
    }
}
