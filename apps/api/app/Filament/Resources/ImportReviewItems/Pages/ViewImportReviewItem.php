<?php

namespace App\Filament\Resources\ImportReviewItems\Pages;

use App\Filament\Resources\ImportReviewItems\ImportReviewItemResource;
use Filament\Resources\Pages\ViewRecord;

class ViewImportReviewItem extends ViewRecord
{
    protected static string $resource = ImportReviewItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportReviewItemResource::publishAction(),
            ImportReviewItemResource::acceptAction(),
            ImportReviewItemResource::keepAction(),
            ImportReviewItemResource::hideAction(),
            ImportReviewItemResource::dismissAction(),
        ];
    }
}
