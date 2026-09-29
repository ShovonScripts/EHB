<?php

namespace App\Filament\Resources\ContentItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ContentItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->title),

                BadgeColumn::make('content_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'news',
                        'danger' => 'investigation',
                        'warning' => 'interview',
                        'success' => 'opinion',
                        'info' => 'video',
                        'gray' => 'photo_story',
                        'secondary' => 'other',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state))),

                BadgeColumn::make('source_type')
                    ->label('Source')
                    ->colors([
                        'success' => 'internal',
                        'warning' => 'external',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'scheduled',
                        'success' => 'published',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('publication.name')
                    ->label('Publication')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),

                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('author.name')
                    ->label('Author')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('published_at', 'desc')
            ->emptyStateHeading('No content items yet')
            ->emptyStateDescription('Create your first piece of journalism to see it here.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->filters([
                SelectFilter::make('content_type')
                    ->label('Content Type')
                    ->options([
                        'news' => 'News Article',
                        'investigation' => 'Investigation',
                        'interview' => 'Interview',
                        'opinion' => 'Opinion / Analysis',
                        'video' => 'Video',
                        'photo_story' => 'Photo Story',
                        'other' => 'Other Work',
                    ]),

                SelectFilter::make('source_type')
                    ->label('Source Type')
                    ->options([
                        'internal' => 'Internal',
                        'external' => 'External',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'published' => 'Published',
                    ])
                    ->default('published'),

                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Category'),

                SelectFilter::make('publication')
                    ->relationship('publication', 'name')
                    ->label('Publication'),

                TernaryFilter::make('is_featured')
                    ->label('Featured')
                    ->placeholder('All')
                    ->trueLabel('Yes')
                    ->falseLabel('No'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
