<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lots\Tables;

use App\Actions\Inventory\RecordWastage;
use App\Enums\LotStatus;
use App\Enums\StorageLocation;
use App\Models\Lot;
use App\Models\User;
use App\Support\Weight;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class LotsTable
{
    public static function configure(Table $table): Table
    {
        $lb = fn (Weight $state) => rtrim(rtrim($state->toDecimal(), '0'), '.').' lb';

        return $table
            ->defaultSort('use_by_date')
            ->columns([
                TextColumn::make('lot_number')->label('Lot')->searchable()->weight('bold'),
                TextColumn::make('product.name')->label('Product')->sortable(),
                TextColumn::make('animal.tag_id')->label('Animal')->placeholder('—'),
                TextColumn::make('storage_location')->formatStateUsing(fn (StorageLocation $state) => $state->label()),
                TextColumn::make('use_by_date')->date()->sortable()
                    ->color(fn (Lot $record) => $record->use_by_date->isPast() ? 'danger' : null),
                TextColumn::make('on_hand_weight_lb')->label('On hand')->formatStateUsing($lb),
                TextColumn::make('reserved_weight_lb')->label('Reserved')->formatStateUsing($lb),
                TextColumn::make('available')->label('Available')->state(fn (Lot $record) => $lb($record->availableWeight())),
                TextColumn::make('status')->badge()->formatStateUsing(fn (LotStatus $state) => $state->label())
                    ->color(fn (LotStatus $state) => match ($state) {
                        LotStatus::Active => 'success',
                        LotStatus::Depleted => 'gray',
                        LotStatus::Withdrawn => 'danger',
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('recordWastage')
                    ->label('Record wastage')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->authorize(fn (Lot $record) => auth()->user()?->can('recordWastage', $record) ?? false)
                    ->schema([
                        TextInput::make('weight_lb')->label('Wastage (lb)')->required()
                            ->rule('regex:/^\d{1,5}(\.\d{1,3})?$/'),
                        Textarea::make('note')->label('Reason')->required()->rows(2),
                    ])
                    ->action(function (Lot $record, array $data): void {
                        /** @var User $user */
                        $user = auth()->user();
                        try {
                            app(RecordWastage::class)->handle($record, Weight::pounds((string) $data['weight_lb']), $user, (string) $data['note']);
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title('Wastage not recorded')->body(collect($e->errors())->flatten()->implode(' '))->send();

                            return;
                        }
                        Notification::make()->success()->title('Wastage recorded')->send();
                    }),
            ]);
        // No delete: the movement ledger must stay intact — withdraw a lot's status instead.
    }
}
