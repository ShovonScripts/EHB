<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\Schemas\SettingForm;
use App\Filament\Resources\Settings\SettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;

    /**
     * Build the form for *this* record's key.
     *
     * The resource's static `form()` has no access to the record, so the
     * preset lookup happens here where the key is known. That is what turns a
     * bare "Value" box into a labelled control — an image picker for the share
     * image, a toggle for the indexable switch, and so on.
     *
     * Validation is not attached here. Each control carries the rules for its
     * declared type (`SettingForm::rulesFor()`), so the page and the list's
     * edit modal — which build the same form from the same key — are validated
     * identically. A rule owned by this page would have been silently absent
     * from the modal, which is exactly the hole this form was written to close.
     */
    public function form(Schema $schema): Schema
    {
        return SettingForm::configure($schema, $this->getRecord()?->key);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
