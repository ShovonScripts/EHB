<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\CreateSetting;
use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Filament\Resources\Settings\Pages\ListSettings;
use App\Filament\Resources\Settings\Schemas\SettingForm;
use App\Filament\Resources\Settings\Tables\SettingsTable;
use App\Models\Setting;
use App\Support\SeoSettings;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'SEO & Settings';

    /**
     * The column the table sorts on and the global search matches against, so
     * it has to be a real, indexed column. The *title* Filament shows is
     * humanised by `getRecordTitle()` below.
     */
    protected static ?string $recordTitleAttribute = 'key';

    /**
     * The row's title, as a person reads it.
     *
     * Everything Filament labels a record with — the edit page's heading and
     * breadcrumb, the list's edit modal ("Edit ..."), the delete confirmation,
     * a global search hit — came from `$recordTitleAttribute`, which is the
     * raw key. Every one of those read `Edit default_og_image_media_id`.
     *
     * The preset's label is the name the same screen already prints in its
     * first column, so it is the name to use here: "Edit Default share image".
     * A key the preset does not know falls back to itself, which keeps a
     * legacy row identifiable rather than hiding what it is.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record === null) {
            return static::getModelLabel();
        }

        return SeoSettings::label((string) $record->getAttribute('key'));
    }

    public static function form(Schema $schema): Schema
    {
        return SettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SettingsTable::configure($table);
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
            'index' => ListSettings::route('/'),
            'create' => CreateSetting::route('/create'),
            'edit' => EditSetting::route('/{record}/edit'),
        ];
    }
}
