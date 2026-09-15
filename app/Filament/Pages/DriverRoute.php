<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Delivery\MarkDelivered;
use App\Actions\Delivery\MarkDeliveryFailed;
use App\Actions\Delivery\MarkOutForDelivery;
use App\Enums\FulfilmentStatus;
use App\Enums\Permission;
use App\Models\Order;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
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
 * Guideline ch. 6, Sprint 05 DoD: the driver's screen works on a phone, outdoors, one-handed, and
 * never shows a price — only what's needed to find the door and prove the handoff. Deliberately not
 * built on OrderResource (which Driver has no permission for anyway, per RolesAndPermissionsSeeder) so
 * price columns are never even queried into this view.
 */
class DriverRoute extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected string $view = 'filament.pages.driver-route';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $title = "Today's deliveries";

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageDeliveries->value) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Order::query()->where('fulfilment', 'delivery')
                ->whereIn('fulfilment_status', [FulfilmentStatus::AwaitingFulfilment, FulfilmentStatus::OutForDelivery, FulfilmentStatus::DeliveryFailed])
                ->whereHas('deliverySlot', fn ($q) => $q->whereDate('date', now()->toDateString()))
                ->with('deliverySlot'))
            ->poll('20s')
            ->contentGrid(['default' => 1])
            ->columns([
                TextColumn::make('customer_name')->label('Customer')->weight('bold')->size('lg'),
                TextColumn::make('customer_phone')->label('Phone')->url(fn (Order $record) => 'tel:'.$record->customer_phone)->icon('heroicon-o-phone'),
                TextColumn::make('address')->label('Address')->state(fn (Order $record) => $record->fullDeliveryAddress())->wrap(),
                TextColumn::make('window')->label('Window')->state(fn (Order $record) => $record->deliverySlot?->label() ?? '—'),
                TextColumn::make('fulfilment_status')->label('Status')->badge()
                    ->formatStateUsing(fn (FulfilmentStatus $state) => $state->label())
                    ->color(fn (FulfilmentStatus $state) => $state->color()),
            ])
            ->recordActions([
                Action::make('dispatch')
                    ->label('Start delivery')
                    ->icon('heroicon-o-truck')
                    ->size('lg')
                    ->color('primary')
                    ->visible(fn (Order $record) => in_array($record->fulfilment_status, [FulfilmentStatus::AwaitingFulfilment, FulfilmentStatus::DeliveryFailed], true))
                    ->action(function (Order $record): void {
                        /** @var User $user */
                        $user = auth()->user();
                        try {
                            app(MarkOutForDelivery::class)->handle($record, $user);
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title('Could not start')->body(collect($e->errors())->flatten()->implode(' '))->send();

                            return;
                        }
                        Notification::make()->success()->title('On the way')->send();
                    }),

                Action::make('delivered')
                    ->label('Delivered')
                    ->icon('heroicon-o-check-circle')
                    ->size('lg')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->fulfilment_status === FulfilmentStatus::OutForDelivery)
                    ->schema([
                        TextInput::make('otp')->label("Customer's code")->inputMode('numeric'),
                        FileUpload::make('proof_photo_path')->label('Or a photo at the door')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('local')->visibility('private')->directory('delivery-proof')->maxSize(10240),
                    ])
                    ->action(function (Order $record, array $data): void {
                        /** @var User $user */
                        $user = auth()->user();
                        try {
                            app(MarkDelivered::class)->handle($record, $user, $data['otp'] ?: null, $data['proof_photo_path'] ?? null);
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title("That didn't work")->body(collect($e->errors())->flatten()->implode(' '))->send();

                            return;
                        }
                        Notification::make()->success()->title('Delivered — nice work')->send();
                    }),

                Action::make('failed')
                    ->label('No one home')
                    ->icon('heroicon-o-x-circle')
                    ->size('lg')
                    ->color('danger')
                    ->visible(fn (Order $record) => $record->fulfilment_status === FulfilmentStatus::OutForDelivery)
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('note')->label('Quick note')->required()->rows(2)->default('No answer at the door'),
                    ])
                    ->action(function (Order $record, array $data): void {
                        /** @var User $user */
                        $user = auth()->user();
                        app(MarkDeliveryFailed::class)->handle($record, $user, (string) $data['note']);
                        Notification::make()->warning()->title('Noted — head back to the shop')->send();
                    }),
            ])
            ->emptyStateHeading('Nothing on your route today');
    }
}
