<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\RecordQcCheck;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/**
 * Guideline ch. 6, Sprint 04: the day's queue, one place a butcher/manager can run it from. Polls for
 * updates (guideline: must keep working even if a websocket drops — this build skips Reverb
 * altogether per the owner's call, recorded 2026-09-15, and polls instead).
 */
class ProductionBoard extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected string $view = 'filament.pages.production-board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $title = 'Production board';

    public string $date = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::RecordWeights->value) ?? false;
    }

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    /** @var list<OrderStatus> statuses no longer queued for the day */
    private const INACTIVE_STATUSES = [OrderStatus::PaymentFailed, OrderStatus::AuthorizationExpired, OrderStatus::Cancelled];

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Order::query()->whereDate('scheduled_date', $this->date)->whereNotIn('status', self::INACTIVE_STATUSES)->with('items'))
            ->poll('10s')
            ->defaultSort('number')
            ->columns([
                TextColumn::make('number')->label('Order')->weight('bold'),
                TextColumn::make('customer_name')->label('Customer')->description(fn (Order $record) => $record->customer_phone),
                TextColumn::make('items')->label('Items')->state(
                    fn (Order $record) => $record->items->map(fn ($item) => "{$item->quantity} × {$item->product_name}")->implode(', ')
                )->wrap(),
                TextColumn::make('minutes')->label('Butcher-min')->state(fn (Order $record) => $record->totalProcessingMinutes()),
                TextColumn::make('status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())->color(fn (OrderStatus $state) => $state->color()),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),

                Action::make('recordQc')
                    ->label('QC check')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->authorize(fn (Order $record) => auth()->user()?->can('recordQcCheck', $record) ?? false)
                    ->visible(fn (Order $record) => $record->status->acceptsQcCheck() && $record->allItemsWeighed())
                    ->schema([
                        Checkbox::make('checklist.weight_matches')->label('Weight matches the ticket')->default(true),
                        Checkbox::make('checklist.no_contamination')->label('No visible contamination or damage')->default(true),
                        Checkbox::make('checklist.packaging_correct')->label('Packaging intact and labeled correctly')->default(true),
                        Checkbox::make('checklist.halal_marking')->label('Zabiha/halal marking correct')->default(true),
                        TextInput::make('temperature_f')->label('Temperature (°F)')->required()->rule('regex:/^-?\d{1,3}(\.\d)?$/'),
                        Radio::make('passed')->label('Result')->options(['1' => 'Pass', '0' => 'Fail'])->required()->inline(),
                        Textarea::make('note')->label('Notes (required on a fail)')->rows(2),
                    ])
                    ->action(function (Order $record, array $data): void {
                        $passed = (bool) $data['passed'];
                        if (! $passed && blank($data['note'] ?? null)) {
                            Notification::make()->danger()->title('Add a note explaining the failure')->send();

                            return;
                        }
                        /** @var User $user */
                        $user = auth()->user();
                        try {
                            app(RecordQcCheck::class)->handle($record, $passed, $data['checklist'] ?? [], $user, $data['temperature_f'], $data['note'] ?? null);
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title('QC check not recorded')->body(collect($e->errors())->flatten()->implode(' '))->send();

                            return;
                        }
                        Notification::make()->success()->title($passed ? 'QC passed' : 'QC failed')->send();
                    }),

                Action::make('finalize')
                    ->label('Finalize & charge')
                    ->icon('heroicon-o-credit-card')
                    ->color('primary')
                    ->authorize(fn (Order $record) => auth()->user()?->can('finalize', $record) ?? false)
                    ->visible(fn (Order $record) => $record->status === OrderStatus::QcPassed)
                    ->requiresConfirmation()
                    ->modalDescription('Weights will be locked and the card charged for the actual weight. This cannot be undone.')
                    ->action(function (Order $record): void {
                        /** @var User $user */
                        $user = auth()->user();
                        try {
                            app(FinalizeOrder::class)->handle($record, $user);
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title('Can’t finalize yet')->body(collect($e->errors())->flatten()->implode(' '))->send();

                            return;
                        }
                        Notification::make()->success()->title('Charging the card')->send();
                    }),
            ])
            ->emptyStateHeading('Nothing scheduled for this day');
    }
}
