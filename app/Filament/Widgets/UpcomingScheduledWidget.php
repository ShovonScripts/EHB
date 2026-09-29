<?php

namespace App\Filament\Widgets;

use App\Models\ContentItem;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingScheduledWidget extends TableWidget
{
    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ContentItem::query()
                ->where('status', 'scheduled')
                ->whereNotNull('published_at')
                ->where('published_at', '>', now())
                ->orderBy('published_at')
                ->limit(5))
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->title)
                    ->url(fn ($record) => route('filament.admin.resources.content-items.edit', $record)),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'scheduled',
                        'success' => 'published',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('published_at')
                    ->label('Scheduled')
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Nothing scheduled')
            ->emptyStateDescription('Set a future publish date to see upcoming items here.')
            ->emptyStateIcon('heroicon-o-calendar');
    }
}
