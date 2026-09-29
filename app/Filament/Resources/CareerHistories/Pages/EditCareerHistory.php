<?php

namespace App\Filament\Resources\CareerHistories\Pages;

use App\Filament\Resources\CareerHistories\CareerHistoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCareerHistory extends EditRecord
{
    protected static string $resource = CareerHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
