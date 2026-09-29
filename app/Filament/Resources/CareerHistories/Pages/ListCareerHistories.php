<?php

namespace App\Filament\Resources\CareerHistories\Pages;

use App\Filament\Resources\CareerHistories\CareerHistoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCareerHistories extends ListRecords
{
    protected static string $resource = CareerHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
