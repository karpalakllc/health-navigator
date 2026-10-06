<?php

namespace App\Filament\Resources\ForumTopics\Pages;

use App\Filament\Resources\ForumTopics\ForumTopicResource;
use App\Models\ForumTopic;
use App\Support\Forum\ForumTagNormalizer;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Resources\Pages\ViewRecord;

class ViewForumTopic extends ViewRecord
{
    protected static string $resource = ForumTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Keywords feed the page title, tag pages and „Слични теми“
            // (docs/seo.md). Saving here confirms them, which is what lets a
            // keyword spelling a doctor's name link the topic to that profile.
            Action::make('editKeywords')
                ->label('Keywords')
                ->icon('heroicon-o-tag')
                ->visible(fn (ForumTopic $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->fillForm(fn (ForumTopic $record): array => [
                    'tags' => $record->tags()->pluck('name')->all(),
                ])
                ->form([
                    TagsInput::make('tags')
                        ->label('Keywords')
                        ->helperText('Up to '.ForumTagNormalizer::MAX_PER_TOPIC.' short phrases people would search for, e.g. „проширени вени“. Cyrillic or Latin; spellings are matched automatically. Saving confirms them.')
                        ->nestedRecursiveRules(['string', 'max:'.ForumTagNormalizer::MAX_LENGTH])
                        ->rules(['array', 'max:'.ForumTagNormalizer::MAX_PER_TOPIC]),
                ])
                ->action(function (ForumTopic $record, array $data): void {
                    abort_unless(auth()->user()?->can('update', $record), 403);

                    $record->syncTags($data['tags'] ?? [], confirmed: true);
                })
                ->successNotificationTitle('Keywords saved'),
        ];
    }
}
