<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationTemplates;

use App\Filament\Resources\NotificationTemplates\Pages\EditNotificationTemplate;
use App\Filament\Resources\NotificationTemplates\Pages\ListNotificationTemplates;
use App\Filament\Resources\NotificationTemplates\Schemas\NotificationTemplateForm;
use App\Filament\Resources\NotificationTemplates\Tables\NotificationTemplatesTable;
use App\Models\NotificationTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Notification Templates';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return NotificationTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationTemplates::route('/'),
            'edit' => EditNotificationTemplate::route('/{record}/edit'),
        ];
    }
}
