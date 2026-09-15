<?php

declare(strict_types=1);

namespace App\Filament\Resources\StoreCreditAccounts;

use App\Filament\Resources\StoreCreditAccounts\Pages\ListStoreCreditAccounts;
use App\Filament\Resources\StoreCreditAccounts\Pages\ViewStoreCreditAccount;
use App\Filament\Resources\StoreCreditAccounts\RelationManagers\EventsRelationManager;
use App\Filament\Resources\StoreCreditAccounts\Tables\StoreCreditAccountsTable;
use App\Models\StoreCreditAccount;
use App\Support\Cents;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StoreCreditAccountResource extends Resource
{
    protected static ?string $model = StoreCreditAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?string $navigationLabel = 'Store Credit';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $recordTitleAttribute = 'customer_email';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return StoreCreditAccountsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Store credit')->columns(2)->schema([
                TextEntry::make('customer_email')->label('Customer'),
                TextEntry::make('balance_cents')->label('Balance')->formatStateUsing(fn (int $state) => Cents::format($state)),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreCreditAccounts::route('/'),
            'view' => ViewStoreCreditAccount::route('/{record}'),
        ];
    }
}
