<?php

declare(strict_types=1);

namespace App\Filament\Resources\PrivacyRequests\Pages;

use App\Filament\Resources\PrivacyRequests\PrivacyRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListPrivacyRequests extends ListRecords
{
    protected static string $resource = PrivacyRequestResource::class;
}
