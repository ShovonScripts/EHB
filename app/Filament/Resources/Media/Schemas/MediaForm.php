<?php

namespace App\Filament\Resources\Media\Schemas;

use App\Filament\Forms\Components\SecureFileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options([
                        'image' => 'Image',
                        'video' => 'Video',
                        'audio' => 'Audio',
                        'document' => 'Document (PDF)',
                    ])
                    ->required()
                    ->live(),

                SecureFileUpload::make('file_path')
                    ->label('File')
                    ->disk('public')
                    ->directory('media')
                    // SECURITY.md §10–§11: the allowlist and size ceiling are
                    // enforced again server-side against the real file bytes
                    // by SecureUpload, and images are re-encoded (EXIF stripped).
                    ->acceptedFileTypes([
                        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
                        'video/mp4', 'video/webm', 'video/quicktime',
                        'audio/mpeg', 'audio/wav', 'audio/ogg',
                        'application/pdf',
                    ])
                    ->maxSize(51200) // 50MB
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('original_filename')
                    ->label('Original Filename')
                    ->required(),

                TextInput::make('alt_text')
                    ->label('Alt Text')
                    ->required(fn (callable $get) => $get('type') === 'image')
                    ->helperText('Required for images (accessibility & SEO)'),

                Textarea::make('caption')
                    ->rows(2)
                    ->columnSpanFull(),

                Select::make('uploaded_by')
                    ->relationship('uploader', 'name')
                    ->default(auth()->id())
                    ->required()
                    ->disabled(),
            ]);
    }
}
