<?php

namespace App\Filament\Resources\Education\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EducationTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('institution')
                    ->label('Institution')
                    ->weight('medium')
                    ->searchable(),

                TextColumn::make('program')
                    ->label('Program')
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label('Start')
                    ->date('M Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End / Graduation')
                    ->date('M Y')
                    ->placeholder('Present')
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
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
