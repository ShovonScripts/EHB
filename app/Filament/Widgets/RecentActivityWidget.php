<?php

namespace App\Filament\Widgets;

use App\Models\ActivityLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentActivityWidget extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ActivityLog::query()
                ->with('user')
                ->latest()
                ->limit(10))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->width('150px'),

                TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System')
                    ->width('120px'),

                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'published' => 'success',
                        'scheduled' => 'warning',
                        default => 'gray',
                    })
                    ->width('100px'),

                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (string $state): string => class_basename(str_replace('App\\Models\\', '', $state)))
                    ->width('120px'),

                TextColumn::make('changes_summary')
                    ->label('Summary')
                    ->getStateUsing(fn ($record) => $this->summarizeChanges($record->changes))
                    ->limit(40)
                    ->tooltip(fn ($record) => $this->summarizeChanges($record->changes)),
            ])
            ->paginated(false)
            ->emptyStateHeading('No activity yet')
            ->emptyStateDescription('Actions on content, profile, and settings will appear here.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    private function summarizeChanges(array $changes = []): string
    {
        if (empty($changes)) {
            return 'No changes recorded';
        }

        $parts = [];

        foreach ($changes as $key => $value) {
            if (is_array($value)) {
                $parts[] = $key.': '.count($value).' changes';
            } else {
                $parts[] = $key;
            }
        }

        return implode(', ', array_slice($parts, 0, 3));
    }
}
