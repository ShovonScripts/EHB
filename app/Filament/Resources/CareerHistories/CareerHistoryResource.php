<?php

namespace App\Filament\Resources\CareerHistories;

use App\Filament\Resources\CareerHistories\Pages\CreateCareerHistory;
use App\Filament\Resources\CareerHistories\Pages\EditCareerHistory;
use App\Filament\Resources\CareerHistories\Pages\ListCareerHistories;
use App\Filament\Resources\CareerHistories\Schemas\CareerHistoryForm;
use App\Filament\Resources\CareerHistories\Tables\CareerHistoriesTable;
use App\Models\CareerHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CareerHistoryResource extends Resource
{
    protected static ?string $model = CareerHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Journalist';

    protected static ?string $recordTitleAttribute = 'role';

    public static function form(Schema $schema): Schema
    {
        return CareerHistoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CareerHistoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCareerHistories::route('/'),
            'create' => CreateCareerHistory::route('/create'),
            'edit' => EditCareerHistory::route('/{record}/edit'),
        ];
    }
}
