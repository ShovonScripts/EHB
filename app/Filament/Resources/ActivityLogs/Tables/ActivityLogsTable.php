<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System')
                    ->searchable(),

                BadgeColumn::make('action')
                    ->label('Action')
                    ->colors([
                        'success' => 'created',
                        'warning' => 'updated',
                        'danger' => 'deleted',
                        'info' => 'published',
                        'gray' => 'scheduled',
                    ]),

                TextColumn::make('subject_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->searchable(),

                TextColumn::make('subject_id')
                    ->label('ID')
                    ->numeric(),

                TextColumn::make('changes')
                    ->label('Changes')
                    ->limit(50)
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'published' => 'Published',
                        'scheduled' => 'Scheduled',
                    ]),

                SelectFilter::make('subject_type')
                    ->label('Subject Type')
                    ->options([
                        'App\Models\ContentItem' => 'Content Item',
                        'App\Models\Category' => 'Category',
                        'App\Models\Tag' => 'Tag',
                        'App\Models\Topic' => 'Topic',
                        'App\Models\Publication' => 'Publication',
                        'App\Models\Media' => 'Media',
                        'App\Models\JournalistProfile' => 'Journalist Profile',
                        'App\Models\CareerHistory' => 'Career History',
                        'App\Models\Education' => 'Education',
                        'App\Models\Award' => 'Award',
                        'App\Models\Page' => 'Page',
                        'App\Models\Setting' => 'Setting',
                        'App\Models\Redirect' => 'Redirect',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
