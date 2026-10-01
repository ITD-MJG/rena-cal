<?php

namespace App\Filament\Dashboard\Resources\Worksheets\Pages;

use App\Filament\Dashboard\Resources\Worksheets\WorksheetResource;
use App\Services\WorksheetService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateWorksheet extends CreateRecord
{
    protected static string $resource = WorksheetResource::class;

    /**
     * The uploaded file is not a model column, so it never reaches
     * handleRecordCreation. Parse it here and build the record through the
     * service.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $path = Storage::disk('public')->path($data['file']);

        $worksheet = app(WorksheetService::class)->createFromUpload([
            'file_path' => $path,
            'original_filename' => basename($data['file']),
            'device_id' => $data['device_id'] ?? null,
            'cert_number' => $data['cert_number'] ?? null,
            'order_number' => $data['order_number'] ?? null,
        ]);

        if ($worksheet->payload === null) {
            Notification::make()
                ->title('Gagal membaca berkas')
                ->body("Lembar kerja \"{$worksheet->original_filename}\" tidak dapat dibaca. Periksa kembali format berkasnya, lalu jalankan Parse Ulang.")
                ->danger()
                ->persistent()
                ->send();
        }

        return $worksheet;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
