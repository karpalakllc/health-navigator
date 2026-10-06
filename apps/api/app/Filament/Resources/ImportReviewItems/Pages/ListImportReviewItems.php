<?php

namespace App\Filament\Resources\ImportReviewItems\Pages;

use App\Enums\ImportReviewKind;
use App\Filament\Resources\ImportReviewItems\ImportReviewItemResource;
use App\Models\ImportReviewItem;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListImportReviewItems extends ListRecords
{
    protected static string $resource = ImportReviewItemResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $open = ImportReviewItem::query()->open()->selectRaw('kind, count(*) as aggregate')->groupBy('kind')->pluck('aggregate', 'kind');

        $tabs = ['all' => Tab::make('All')];

        foreach (ImportReviewKind::cases() as $kind) {
            $tabs[$kind->value] = Tab::make($kind->label())
                ->badge(fn (): ?string => ($open[$kind->value] ?? 0) > 0 ? (string) $open[$kind->value] : null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kind', $kind->value));
        }

        return $tabs;
    }
}
