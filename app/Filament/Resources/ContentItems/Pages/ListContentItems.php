<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Filament\Resources\ContentItems\ContentItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListContentItems extends ListRecords
{
    protected static string $resource = ContentItemResource::class;

    /**
     * Content-type scoped views (ADMIN_PANEL.md §1).
     *
     * Filament v5 resolves tabs through the `activeTab` Livewire property
     * (synced to the `?tab=` query string) and applies each Tab's
     * `modifyQueryUsing()` to the table query — so the scoping has to live on
     * the Tab itself, not on a request query string.
     *
     * @var array<string, string>
     */
    private const CONTENT_TYPE_TABS = [
        'news' => 'News',
        'investigation' => 'Investigations',
        'interview' => 'Interviews',
        'opinion' => 'Opinions',
        'video' => 'Video',
        'photo_story' => 'Photo Stories',
        'other' => 'Other',
    ];

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All'),
        ];

        foreach (self::CONTENT_TYPE_TABS as $contentType => $label) {
            $tabs[$contentType] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('content_type', $contentType));
        }

        return $tabs;
    }

    public function mount(): void
    {
        parent::mount();

        // Support deep links such as /admin/content-items?content_type=interview
        // (used by the per-type dashboard links) alongside the ?tab= key that
        // Filament syncs `activeTab` to. An explicit ?tab= always wins.
        $contentType = request()->query('content_type');

        if (
            blank(request()->query('tab'))
            && is_string($contentType)
            && array_key_exists($contentType, self::CONTENT_TYPE_TABS)
        ) {
            $this->activeTab = $contentType;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
