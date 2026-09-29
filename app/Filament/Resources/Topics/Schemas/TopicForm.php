<?php

namespace App\Filament\Resources\Topics\Schemas;

use App\Filament\Forms\Components\MediaFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TopicForm
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

                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),

                MediaFileUpload::make('featured_image_media_id')
                    ->label('Featured Image')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('topics/featured')
                    ->altTextField('featured_image_alt_text')
                    ->columnSpanFull(),

                TextInput::make('featured_image_alt_text')
                    ->label('Featured Image Alt Text')
                    ->placeholder('Describe the featured image for accessibility')
                    ->columnSpanFull()
                    ->helperText('Required for accessibility when featured image is set'),
            ]);
    }
}
