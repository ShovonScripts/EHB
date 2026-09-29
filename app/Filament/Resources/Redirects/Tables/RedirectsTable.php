<?php

namespace App\Filament\Resources\Redirects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_path')
                    ->label('From')
                    ->weight('medium')
                    ->searchable()
                    ->copyable()
                    ->badge(),

                TextColumn::make('to_path')
                    ->label('To')
                    ->searchable()
                    ->copyable()
                    ->badge(),

                BadgeColumn::make('status_code')
                    ->label('Code')
                    ->colors([
                        'success' => '301',
                        'warning' => '302',
                    ])
                    ->formatStateUsing(fn ($state) => (int) $state === 301 ? '301 Permanent' : '302 Temporary'),
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
