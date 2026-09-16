<?php

namespace App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages;

use App\Filament\Dashboard\Resources\CalibrationWorksheets\CalibrationWorksheetResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCalibrationWorksheet extends ViewRecord
{
    protected static string $resource = CalibrationWorksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('recalculate')
                ->label('Hitung Ulang')
                ->icon('heroicon-m-arrow-path')
                ->requiresConfirmation()
                ->action(fn () => $this->record->recalculate()),
            Actions\Action::make('generateCertificate')
                ->label('Generate Sertifikat')
                ->icon('heroicon-m-document-arrow-down')
                ->color('success')
                ->action(function () {
                    $worksheet = $this->record->load([
                        'device.deviceName',
                        'device.brand',
                        'device.type',
                        'device.customer',
                        'service',
                        'instruments',
                        'physicalInspections',
                        'electricalSafetyTests',
                        'performanceMeasurements.readings',
                        'uncertaintyBudgets.components',
                    ]);

                    $pdf = Pdf::loadView('pdf.certificate', ['worksheet' => $worksheet])
                        ->setPaper('a4', 'portrait');

                    $filename = 'Sertifikat_Kalibrasi_'.$worksheet->device->device_number.'.pdf';

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, $filename);
                }),
        ];
    }
}
