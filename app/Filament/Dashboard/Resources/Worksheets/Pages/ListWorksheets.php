<?php

namespace App\Filament\Dashboard\Resources\Worksheets\Pages;

use App\Filament\Dashboard\Resources\Worksheets\WorksheetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorksheets extends ListRecords
{
    protected static string $resource = WorksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
