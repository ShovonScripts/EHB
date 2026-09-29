<?php

namespace App\Filament\Widgets;

use App\Models\Contact;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UnreadContactStats extends BaseWidget
{
    protected static ?int $sort = 5;

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
        $unreadCount = Contact::where('is_read', false)->count();
        $totalCount = Contact::count();
        $thisWeekCount = Contact::where('created_at', '>=', now()->subDays(7))->count();

        return [
            Stat::make('Unread Messages', $unreadCount)
                ->description($unreadCount === 0 ? 'Inbox zero — all caught up' : 'Awaiting your response')
                ->descriptionIcon($unreadCount === 0 ? 'heroicon-m-check-badge' : 'heroicon-m-envelope')
                ->color($unreadCount > 0 ? 'danger' : 'success')
                ->url(route('filament.admin.resources.contacts.index')),

            Stat::make('Total Inquiries', $totalCount)
                ->description('All reader submissions')
                ->descriptionIcon('heroicon-m-inbox-stack')
                ->color('gray')
                ->url(route('filament.admin.resources.contacts.index')),

            Stat::make('Recent (7 Days)', $thisWeekCount)
                ->description('Recent activity')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->url(route('filament.admin.resources.contacts.index')),
        ];
    }
}
