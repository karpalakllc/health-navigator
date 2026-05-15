<?php

namespace App\Filament\Resources\ForumCategories\Pages;

use App\Filament\Resources\ForumCategories\ForumCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateForumCategory extends CreateRecord
{
    protected static string $resource = ForumCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['is_published']) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
