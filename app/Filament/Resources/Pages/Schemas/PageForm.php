<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Filament\Forms\Components\MediaFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('seo_title')
                    ->default(null),
                Textarea::make('seo_description')
                    ->default(null)
                    ->columnSpanFull(),
                MediaFileUpload::make('seo_og_image_media_id')
                    ->label('Open Graph Image')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('pages/og')
                    ->altTextField('seo_og_image_alt_text')
                    ->columnSpanFull(),

                TextInput::make('seo_og_image_alt_text')
                    ->label('OG Image Alt Text')
                    ->placeholder('Describe the OG image for social sharing')
                    ->columnSpanFull()
                    ->helperText('Required for accessibility when OG image is set'),
                TextInput::make('canonical_url_override')
                    ->default(null),
            ]);
    }
}
