<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Actions\Orders\RecordWeight;
use App\Enums\WeightSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Cents;
use App\Support\Weight;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items & weights';

    /** Staff who can view the order can see its items, even if they can't edit the order itself. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $lb = fn (?Weight $state) => $state === null ? '—' : rtrim(rtrim($state->toDecimal(), '0'), '.').' lb';

        return $table
            ->recordTitleAttribute('product_name')
            ->columns([
                TextColumn::make('product_name')->label('Product')->weight('bold')
                    ->description(fn (OrderItem $record) => Cents::format($record->price_per_lb_cents).' / lb'),
                TextColumn::make('lot.lot_number')->label('Lot')->placeholder('Not tracked')
                    ->description(fn (OrderItem $record) => $record->lot?->animal?->tag_id ? "Animal {$record->lot->animal->tag_id}" : null),
                TextColumn::make('quantity')->label('Pieces')->alignCenter(),
                TextColumn::make('estimated_weight_lb')->label('Est. weight')->formatStateUsing($lb),
                TextColumn::make('estimated_cents')->label('Estimate')->formatStateUsing(fn (int $state) => Cents::format($state)),
                TextColumn::make('actual_weight_lb')->label('Actual weight')->formatStateUsing($lb)->placeholder('Not weighed')
                    ->color(fn (OrderItem $record) => $record->actual_weight_lb === null ? 'danger' : 'success'),
                TextColumn::make('final_cents')->label('Actual price')->formatStateUsing(fn (?int $state) => Cents::format($state))->placeholder('—'),
            ])
            ->recordActions([
                Action::make('recordWeight')
                    ->label(fn (OrderItem $record) => $record->actual_weight_lb === null ? 'Record weight' : 'Correct weight')
                    ->icon('heroicon-o-scale')
                    ->authorize(fn () => $this->canRecordWeight())
                    ->visible(fn () => $this->canRecordWeight())
                    ->schema([
                        TextInput::make('weight_lb')
                            ->label('Actual weight (lb)')
                            ->required()
                            ->rule('regex:/^\d{1,4}(\.\d{1,3})?$/')
                            ->validationMessages(['regex' => 'Enter pounds with up to 3 decimals, e.g. 3.742'])
                            ->inputMode('decimal')
                            ->autofocus(),
                        TextInput::make('note')->label('Note (required for a correction)')->maxLength(255),
                    ])
                    ->action(function (OrderItem $record, array $data): void {
                        if ($record->actual_weight_lb !== null && blank($data['note'] ?? null)) {
                            Notification::make()->danger()->title('Add a note explaining the correction')->send();

                            return;
                        }
                        /** @var User $user */
                        $user = auth()->user();
                        try {
                            app(RecordWeight::class)->handle($record, Weight::pounds((string) $data['weight_lb']), WeightSource::Manual, $user, $data['note'] ?? null);
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title('Weight not saved')->body(collect($e->errors())->flatten()->implode(' '))->send();

                            return;
                        }
                        Notification::make()->success()->title('Weight recorded')->send();
                    }),
            ]);
    }

    private function canRecordWeight(): bool
    {
        /** @var Order $order */
        $order = $this->getOwnerRecord();

        return auth()->user()?->can('recordWeight', $order->refresh()) ?? false;
    }
}
