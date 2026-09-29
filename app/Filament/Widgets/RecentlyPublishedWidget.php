<?php

namespace App\Filament\Widgets;

use App\Models\ContentItem;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentlyPublishedWidget extends TableWidget
{
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ContentItem::query()
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->latest('published_at')
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
                    ->label('Published')
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Nothing published yet')
            ->emptyStateDescription('Publish your first piece to see it here.')
            ->emptyStateIcon('heroicon-o-globe-alt');
    }
}
