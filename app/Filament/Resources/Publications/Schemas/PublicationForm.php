<?php

namespace App\Filament\Resources\Publications\Schemas;

use App\Filament\Forms\Components\MediaFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PublicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, $state, callable $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),

                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true),

                MediaFileUpload::make('logo_media_id')
                    ->label('Logo')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('publications/logos')
                    ->altTextField('logo_alt_text')
                    ->columnSpanFull(),

                TextInput::make('logo_alt_text')
                    ->label('Logo Alt Text')
                    ->placeholder('e.g., The Daily Star logo')
                    ->columnSpanFull()
                    ->helperText('Required for accessibility when logo is set'),

                TextInput::make('website_url')
                    ->label('Website URL')
                    ->url()
                    ->placeholder('https://example.com'),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
