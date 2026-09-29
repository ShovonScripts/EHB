<?php

namespace App\Filament\Widgets;

use App\Models\ContentItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContentStatusStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    /**
     * Responsive stat row: one per line on mobile, two on tablet, three when
     * there is room — handled by the schema grid rather than CSS overrides.
     *
     * @return array<string, int>
     */
    protected function getColumns(): array
    {
        return [
            'default' => 1,
            'sm' => 2,
            'lg' => 3,
        ];
    }

    protected function getStats(): array
    {
        $draftCount = ContentItem::where('status', 'draft')->count();
        $scheduledCount = ContentItem::where('status', 'scheduled')->count();
        $publishedCount = ContentItem::where('status', 'published')->count();

        return [
            Stat::make('Draft', $draftCount)
                ->description($draftCount === 0 ? 'No drafts yet' : 'Items not yet published')
                ->descriptionIcon('heroicon-m-pencil')
                ->color('gray'),

            Stat::make('Scheduled', $scheduledCount)
                ->description($scheduledCount === 0 ? 'Nothing scheduled' : 'Items awaiting publication')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Published', $publishedCount)
                ->description($publishedCount === 0 ? 'No live items yet' : 'Live items on the site')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
