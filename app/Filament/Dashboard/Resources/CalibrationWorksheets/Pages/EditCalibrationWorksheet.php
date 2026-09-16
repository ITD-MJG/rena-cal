<?php

namespace App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages;

use App\Calibration\WorksheetHydrator;
use App\Filament\Dashboard\Resources\CalibrationWorksheets\CalibrationWorksheetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCalibrationWorksheet extends EditRecord
{
    protected static string $resource = CalibrationWorksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\Action::make('recalculate')
                ->label('Hitung Ulang')
                ->icon('heroicon-m-arrow-path')
                ->requiresConfirmation()
                ->action(fn () => $this->record->recalculate()),
        ];
    }

    /**
     * Ensure the child rows exist before the form renders. Covers worksheets
     * created before a template gained a parameter, and rows removed by hand.
     */
    protected function beforeFill(): void
    {
        app(WorksheetHydrator::class)->hydrate($this->record);
    }

    protected function afterSave(): void
    {
        $this->record->recalculate();
    }
}
