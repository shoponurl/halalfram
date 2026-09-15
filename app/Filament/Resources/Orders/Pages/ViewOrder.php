<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\ApproveUnderweight;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\RecordQcCheck;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Support\Cents;
use App\Support\CuttingSheetPdf;
use App\Support\SettlementPlan;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
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
        ];
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
