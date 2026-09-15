<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationTemplates\Schemas;

use App\Enums\NotificationEvent;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NotificationTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Template')
                ->description('Placeholders: {{customer_name}} {{order_number}} {{tracking_url}} {{otp}} {{delivery_window}} {{balance_due}} {{refund_amount}} {{pickup_deadline_hours}}')
                ->schema([
                    TextInput::make('event')->disabled()->dehydrated(false)->formatStateUsing(fn (NotificationEvent $state) => $state->label()),
                    TextInput::make('channel')->disabled()->dehydrated(false)->formatStateUsing(fn (string $state) => mb_strtoupper($state)),
                    TextInput::make('subject')->maxLength(190)->helperText('Email only.'),
                    Textarea::make('body')->required()->rows(6),
                    Toggle::make('active')->default(true)->inline(false),
                ]),
        ]);
    }
}
