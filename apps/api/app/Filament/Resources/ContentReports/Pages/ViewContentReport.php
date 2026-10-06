<?php

namespace App\Filament\Resources\ContentReports\Pages;

use App\Filament\Resources\ContentReports\ContentReportResource;
use App\Filament\Support\ContentReportActions;
use Filament\Resources\Pages\ViewRecord;

class ViewContentReport extends ViewRecord
{
    protected static string $resource = ContentReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContentReportActions::hide(),
            ContentReportActions::keep(),
        ];
    }
}
