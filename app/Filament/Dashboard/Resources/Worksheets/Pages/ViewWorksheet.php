<?php

namespace App\Filament\Dashboard\Resources\Worksheets\Pages;

use App\Filament\Dashboard\Resources\Worksheets\WorksheetResource;
use App\Services\WorksheetService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWorksheet extends ViewRecord
{
    protected static string $resource = WorksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('reparse')
                ->label('Parse Ulang')
                ->icon('heroicon-m-arrow-path')
                ->requiresConfirmation()
                ->action(function () {
                    app(WorksheetService::class)->reparse($this->record);

                    Notification::make()
                        ->title('Lembar kerja dibaca ulang')
                        ->success()
                        ->send();

                    return redirect(WorksheetResource::getUrl('view', ['record' => $this->record]));
                }),
            Action::make('generateCertificate')
                ->label('Generate Sertifikat')
                ->icon('heroicon-m-document-arrow-down')
                ->color('success')
                ->action(function () {
                    $worksheet = $this->record->load('device.customer');

                    $pdf = Pdf::loadView('pdf.certificate', ['worksheet' => $worksheet])
                        ->setPaper('a4', 'portrait');

                    $serial = $worksheet->device_serial ?? 'worksheet';
                    $filename = "Sertifikat_Kalibrasi_{$serial}.pdf";

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, $filename);
                }),
        ];
    }
}
