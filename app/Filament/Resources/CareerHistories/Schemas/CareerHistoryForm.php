<?php

namespace App\Filament\Resources\CareerHistories\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CareerHistoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('journalist_profile_id')
                    ->relationship('journalistProfile', 'name')
                    ->required()
                    ->default(fn () => auth()->user()?->journalistProfile?->id),

                TextInput::make('role')
                    ->required()
                    ->placeholder('e.g., Senior Reporter, Bureau Chief'),

                TextInput::make('organization')
                    ->required()
                    ->placeholder('e.g., The Daily Star, BBC'),

                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->required(),

                DatePicker::make('end_date')
                    ->label('End Date')
                    ->placeholder('Leave blank for current position'),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }
}
