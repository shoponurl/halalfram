<?php

declare(strict_types=1);

namespace App\Filament\Resources\StoreCreditAccounts\Pages;

use App\Actions\Payments\IssueStoreCredit;
use App\Filament\Resources\StoreCreditAccounts\StoreCreditAccountResource;
use App\Models\StoreCreditAccount;
use App\Models\User;
use App\Support\DollarInput;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStoreCreditAccounts extends ListRecords
{
    protected static string $resource = StoreCreditAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issueCredit')
                ->label('Issue credit')
                ->icon('heroicon-o-plus-circle')
                ->authorize(fn () => auth()->user()?->can('issueCredit', StoreCreditAccount::class) ?? false)
                ->schema([
                    TextInput::make('customer_email')->label('Customer email')->email()->required(),
                    TextInput::make('amount')->label('Amount ($)')->required()->prefix('$')->rule('regex:/^\$?\d{1,4}(\.\d{1,2})?$/'),
                    Textarea::make('reason')->label('Reason')->required()->rows(2),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    app(IssueStoreCredit::class)->handle(
                        strtolower(trim((string) $data['customer_email'])),
                        DollarInput::toCents((string) $data['amount']),
                        (string) $data['reason'],
                        $user,
                    );
                    Notification::make()->success()->title('Credit issued')->send();
                }),
        ];
    }
}
