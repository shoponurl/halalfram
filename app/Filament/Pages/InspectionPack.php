<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Support\InspectionPackPdf;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/** Guideline ch. 6, Sprint 07: HACCP/inspection pack, generated on demand for a date range. */
class InspectionPack extends Page
{
    protected string $view = 'filament.pages.inspection-pack';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Compliance';

    protected static ?string $title = 'Inspection pack';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageCompliance->value) ?? false;
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Generate PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->schema([
                    DatePicker::make('from')->required()->native(false)->default(now()->subDays(7)),
                    DatePicker::make('to')->required()->native(false)->default(now()),
                ])
                ->action(function (array $data): StreamedResponse {
                    $from = Carbon::parse((string) $data['from']);
                    $to = Carbon::parse((string) $data['to']);

                    return response()->streamDownload(
                        function () use ($from, $to): void {
                            echo app(InspectionPackPdf::class)->render($from, $to)->output();
                        },
                        "inspection-pack-{$from->toDateString()}-to-{$to->toDateString()}.pdf",
                    );
                }),
        ];
    }
}
