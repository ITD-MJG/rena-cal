<?php

namespace App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages;

use App\Filament\Dashboard\Resources\CalibrationWorksheets\CalibrationWorksheetResource;
use Filament\Resources\Pages\CreateRecord;
use YousefAman\FilamentAutosave\HasAutosaveForCreate;

class CreateCalibrationWorksheet extends CreateRecord
{
    use HasAutosaveForCreate;

    protected static string $resource = CalibrationWorksheetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['technician_name'] = auth()->user()->name;

        return $data;
    }
}
