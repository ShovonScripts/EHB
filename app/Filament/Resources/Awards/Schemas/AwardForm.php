<?php

namespace App\Filament\Resources\Awards\Schemas;

use App\Filament\Forms\Components\MediaFileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AwardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('journalist_profile_id')
                    ->relationship('journalistProfile', 'name')
                    ->required()
                    ->default(fn () => auth()->user()?->journalistProfile?->id),

                TextInput::make('title')
                    ->required()
                    ->placeholder('e.g., Best Investigative Report, Journalist of the Year'),

                TextInput::make('awarding_body')
                    ->required()
                    ->placeholder('e.g., UNESCO, Press Institute Bangladesh'),

                TextInput::make('year')
                    ->label('Year')
                    ->numeric()
                    ->required()
                    ->minValue(1900)
                    ->maxValue(date('Y') + 1)
                    ->default(date('Y')),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                TextInput::make('url')
                    ->label('Award URL')
                    ->url()
                    ->placeholder('https://example.com/award-page'),

                MediaFileUpload::make('media_id')
                    ->label('Certificate / Photo')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('awards')
                    ->altTextField('media_alt_text')
                    ->columnSpanFull(),

                TextInput::make('media_alt_text')
                    ->label('Certificate/Photo Alt Text')
                    ->placeholder('Describe the certificate or photo')
                    ->columnSpanFull()
                    ->helperText('Required for accessibility when certificate/photo is set'),

                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }
}
