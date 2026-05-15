<?php

namespace App\Filament\Resources\ForumTopics\Pages;

use App\Filament\Resources\ForumTopics\ForumTopicResource;
use Filament\Resources\Pages\ListRecords;

class ListForumTopics extends ListRecords
{
    protected static string $resource = ForumTopicResource::class;
}
