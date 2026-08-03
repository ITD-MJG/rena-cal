<?php

namespace App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages;

use App\Filament\Dashboard\Resources\CalibrationWorksheets\CalibrationWorksheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCalibrationWorksheet extends ViewRecord
{
    protected static string $resource = CalibrationWorksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
