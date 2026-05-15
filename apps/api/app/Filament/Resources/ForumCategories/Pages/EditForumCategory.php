<?php

namespace App\Filament\Resources\ForumCategories\Pages;

use App\Filament\Resources\ForumCategories\ForumCategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditForumCategory extends EditRecord
{
    protected static string $resource = ForumCategoryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['is_published']) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (empty($data['is_published'])) {
            $data['published_at'] = null;
        }

        return $data;
    }
}
