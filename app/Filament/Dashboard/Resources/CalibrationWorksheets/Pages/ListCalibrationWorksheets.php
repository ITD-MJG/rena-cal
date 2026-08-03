<?php

namespace App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages;

use App\Filament\Dashboard\Resources\CalibrationWorksheets\CalibrationWorksheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCalibrationWorksheets extends ListRecords
{
    protected static string $resource = CalibrationWorksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
