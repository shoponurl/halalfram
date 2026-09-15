<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Delivery\MarkDelivered;
use App\Actions\Delivery\MarkDeliveryFailed;
use App\Actions\Delivery\MarkOutForDelivery;
use App\Actions\Delivery\MarkPickedUp;
use App\Actions\Delivery\MarkReadyForPickup;
use App\Actions\Delivery\RescheduleDelivery;
use App\Actions\Orders\ApproveUnderweight;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\RecordCashPayment;
use App\Actions\Orders\RecordQcCheck;
use App\Enums\FulfilmentStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\DeliverySlot;
use App\Models\Order;
use App\Models\User;
use App\Support\Cents;
use App\Support\CuttingSheetPdf;
use App\Support\DollarInput;
use App\Support\SettlementPlan;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        /** @var Order $order */
        $order = $this->getRecord();

        return "Order {$order->number}";
    }

    protected function getHeaderActions(): array
    {
        return [
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
                    TextInput::make('temperature_f')
                        ->label('Temperature (°F)')
                        ->required()
                        ->rule('regex:/^-?\d{1,3}(\.\d)?$/'),
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
                        app(RecordQcCheck::class)->handle(
                            $record, $passed, $data['checklist'] ?? [], $user,
                            $data['temperature_f'], $data['note'] ?? null,
                        );
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title('QC check not recorded')->body(collect($e->errors())->flatten()->implode(' '))->send();

                        return;
                    }
                    $passed
                        ? Notification::make()->success()->title('QC passed — ready to finalize')->send()
                        : Notification::make()->warning()->title('QC failed — needs re-cutting')->body($data['note'])->persistent()->send();

                    $this->refreshFormData(['status']);
                    $this->redirect(OrderResource::getUrl('view', ['record' => $record]));
                }),

            Action::make('finalize')
                ->label('Finalize & charge card')
                ->icon('heroicon-o-credit-card')
                ->color('primary')
                ->authorize(fn (Order $record) => auth()->user()?->can('finalize', $record) ?? false)
                ->visible(fn (Order $record) => $record->status === OrderStatus::QcPassed)
                ->requiresConfirmation()
                ->modalDescription('Weights will be locked and the card charged for the actual weight. This cannot be undone.')
                ->action(fn (Order $record) => $this->runFinalize(fn (User $user) => app(FinalizeOrder::class)->handle($record, $user))),

            Action::make('approveUnderweight')
                ->label('Approve underweight & charge')
                ->icon('heroicon-o-check-badge')
                ->color('warning')
                ->authorize(fn (Order $record) => auth()->user()?->can('approveUnderweight', $record) ?? false)
                ->visible(fn (Order $record) => $record->status === OrderStatus::NeedsReview)
                ->requiresConfirmation()
                ->modalHeading('Confirm the weights are correct')
                ->modalDescription(fn (Order $record) => 'Actual total '.Cents::format($record->final_cents).' is more than '
                    .rtrim(rtrim($record->underweight_review_pct, '0'), '.').'% below the estimate of '.Cents::format($record->estimated_cents)
                    .'. If a weight was mistyped, close this and record the correct weight instead. Approving charges the lower amount and releases the rest of the hold.')
                ->action(fn (Order $record) => $this->runFinalize(fn (User $user) => app(ApproveUnderweight::class)->handle($record, $user))),

            Action::make('recordCashPayment')
                ->label('Record cash payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->authorize(fn (Order $record) => auth()->user()?->can('recordCashPayment', $record) ?? false)
                ->visible(fn (Order $record) => $record->payment_method === 'cash' && $record->status === OrderStatus::AwaitingCashPayment)
                ->schema([
                    TextInput::make('amount_tendered')
                        ->label('Cash tendered ($)')
                        ->required()
                        ->rule('regex:/^\$?\d{1,4}(\.\d{1,2})?$/')
                        ->prefix('$')
                        ->default(fn (Order $record) => Cents::format($record->final_cents)),
                ])
                ->action(fn (Order $record, array $data) => $this->runFulfilment(
                    fn (User $user) => app(RecordCashPayment::class)->handle($record, DollarInput::toCents((string) $data['amount_tendered']), $user),
                    'Cash payment recorded',
                )),

            Action::make('invoice')
                ->label('Invoice PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn (Order $record) => in_array($record->status, [OrderStatus::Completed, OrderStatus::AwaitingBalance], true))
                ->url(fn (Order $record) => route('orders.invoice', $record)),

            Action::make('cuttingSheet')
                ->label('Cutting sheet')
                ->icon('heroicon-o-scissors')
                ->color('gray')
                ->authorize(fn (Order $record) => auth()->user()?->can('printCuttingSheet', $record) ?? false)
                ->action(fn (Order $record) => response()->streamDownload(
                    function () use ($record) {
                        echo app(CuttingSheetPdf::class)->render($record)->output();
                    },
                    "cutting-sheet-{$record->number}.pdf",
                )),

            Action::make('markReadyForPickup')
                ->label('Mark ready for pickup')
                ->icon('heroicon-o-bell-alert')
                ->color('primary')
                ->authorize(fn (Order $record) => auth()->user()?->can('manageFulfilment', $record) ?? false)
                ->visible(fn (Order $record) => $record->fulfilment === 'pickup' && $record->fulfilment_status === FulfilmentStatus::AwaitingFulfilment)
                ->action(fn (Order $record) => $this->runFulfilment(fn (User $user) => app(MarkReadyForPickup::class)->handle($record, $user), 'Marked ready for pickup')),

            Action::make('markPickedUp')
                ->label('Mark picked up')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->authorize(fn (Order $record) => auth()->user()?->can('manageFulfilment', $record) ?? false)
                ->visible(fn (Order $record) => $record->fulfilment_status === FulfilmentStatus::ReadyForPickup)
                ->action(fn (Order $record) => $this->runFulfilment(fn (User $user) => app(MarkPickedUp::class)->handle($record, $user), 'Marked picked up')),

            Action::make('markOutForDelivery')
                ->label('Dispatch for delivery')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->authorize(fn (Order $record) => auth()->user()?->can('manageFulfilment', $record) ?? false)
                ->visible(fn (Order $record) => $record->fulfilment === 'delivery' && in_array($record->fulfilment_status, [FulfilmentStatus::AwaitingFulfilment, FulfilmentStatus::DeliveryFailed], true))
                ->action(function (Order $record): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $updated = app(MarkOutForDelivery::class)->handle($record, $user);
                    Notification::make()->success()->title('Out for delivery')->body("Code for the customer: {$updated->delivery_otp}")->persistent()->send();
                    $this->redirect(OrderResource::getUrl('view', ['record' => $record]));
                }),

            Action::make('markDelivered')
                ->label('Mark delivered')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->authorize(fn (Order $record) => auth()->user()?->can('manageFulfilment', $record) ?? false)
                ->visible(fn (Order $record) => $record->fulfilment_status === FulfilmentStatus::OutForDelivery)
                ->schema([
                    TextInput::make('otp')->label('Customer\'s code'),
                    FileUpload::make('proof_photo_path')->label('Or a photo')->image()->disk('public')->directory('delivery-proof'),
                ])
                ->action(function (Order $record, array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    try {
                        app(MarkDelivered::class)->handle($record, $user, $data['otp'] ?: null, $data['proof_photo_path'] ?? null);
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title('Not marked delivered')->body(collect($e->errors())->flatten()->implode(' '))->send();

                        return;
                    }
                    Notification::make()->success()->title('Delivered')->send();
                    $this->redirect(OrderResource::getUrl('view', ['record' => $record]));
                }),

            Action::make('markDeliveryFailed')
                ->label('Delivery attempt failed')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->authorize(fn (Order $record) => auth()->user()?->can('manageFulfilment', $record) ?? false)
                ->visible(fn (Order $record) => $record->fulfilment_status === FulfilmentStatus::OutForDelivery)
                ->schema([
                    Textarea::make('note')->label('What happened')->required()->rows(2),
                ])
                ->action(function (Order $record, array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $updated = app(MarkDeliveryFailed::class)->handle($record, $user, (string) $data['note']);
                    $updated->fulfilment_status === FulfilmentStatus::Returned
                        ? Notification::make()->warning()->title('Returned to shop — refund issued minus the delivery fee')->persistent()->send()
                        : Notification::make()->warning()->title('Delivery failed — one free re-attempt left')->send();
                    $this->redirect(OrderResource::getUrl('view', ['record' => $record]));
                }),

            Action::make('rescheduleDelivery')
                ->label('Reschedule delivery')
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->authorize(fn (Order $record) => auth()->user()?->can('manageFulfilment', $record) ?? false)
                ->visible(fn (Order $record) => $record->fulfilment_status === FulfilmentStatus::DeliveryFailed)
                ->schema([
                    Select::make('delivery_slot_id')->label('New window')->required()
                        ->options(fn () => DeliverySlot::query()->upcoming()->get()->mapWithKeys(fn (DeliverySlot $s) => [$s->id => $s->label()])),
                ])
                ->action(function (Order $record, array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    try {
                        app(RescheduleDelivery::class)->handle($record, DeliverySlot::query()->findOrFail((int) $data['delivery_slot_id']), $user);
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title('Not rescheduled')->body(collect($e->errors())->flatten()->implode(' '))->send();

                        return;
                    }
                    Notification::make()->success()->title('Rescheduled')->send();
                    $this->redirect(OrderResource::getUrl('view', ['record' => $record]));
                }),
        ];
    }

    private function runFulfilment(callable $callback, string $successTitle): void
    {
        try {
            $callback(auth()->user());
        } catch (ValidationException $e) {
            Notification::make()->danger()->title('Not updated')->body(collect($e->errors())->flatten()->implode(' '))->send();

            return;
        }
        Notification::make()->success()->title($successTitle)->send();
        $this->redirect(OrderResource::getUrl('view', ['record' => $this->getRecord()]));
    }

    /** @param callable(User): SettlementPlan $callback */
    private function runFinalize(callable $callback): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $plan = $callback($user);
        } catch (ValidationException $e) {
            Notification::make()->danger()->title('Can’t finalize yet')->body(collect($e->errors())->flatten()->implode(' '))->send();

            return;
        }

        $plan->action === SettlementPlan::REVIEW
            ? Notification::make()->warning()->title('Needs manager review')->body($plan->reason)->persistent()->send()
            : Notification::make()->success()->title('Charging '.Cents::format($plan->finalCents))->body($plan->reason)->send();

        $this->refreshFormData(['status']);
        $this->redirect(OrderResource::getUrl('view', ['record' => $this->getRecord()]));
    }
}
