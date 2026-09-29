<?php

namespace App\Filament\Resources\Education\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EducationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('journalist_profile_id')
                    ->relationship('journalistProfile', 'name')
                    ->required()
                    ->default(fn () => auth()->user()?->journalistProfile?->id),

                TextInput::make('institution')
                    ->required()
                    ->placeholder('e.g., University of Dhaka, Columbia Journalism School'),

                TextInput::make('program')
                    ->required()
                    ->placeholder('e.g., MA Journalism, BA Political Science'),

                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->required(),

                DatePicker::make('end_date')
                    ->label('End Date / Graduation Date'),

                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }
}
