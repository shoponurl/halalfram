<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackingRules;

use App\Filament\Resources\PackingRules\Pages\CreatePackingRule;
use App\Filament\Resources\PackingRules\Pages\EditPackingRule;
use App\Filament\Resources\PackingRules\Pages\ListPackingRules;
use App\Filament\Resources\PackingRules\Schemas\PackingRuleForm;
use App\Filament\Resources\PackingRules\Tables\PackingRulesTable;
use App\Models\PackingRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PackingRuleResource extends Resource
{
    protected static ?string $model = PackingRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Shipping';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PackingRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PackingRulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPackingRules::route('/'),
            'create' => CreatePackingRule::route('/create'),
            'edit' => EditPackingRule::route('/{record}/edit'),
        ];
    }
}
