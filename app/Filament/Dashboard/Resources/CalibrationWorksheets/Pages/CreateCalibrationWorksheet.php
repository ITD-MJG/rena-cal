<?php

namespace App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages;

use App\Calibration\TemplateRegistry;
use App\Calibration\WorksheetHydrator;
use App\Filament\Dashboard\Resources\CalibrationWorksheets\CalibrationWorksheetResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use YousefAman\FilamentAutosave\HasAutosaveForCreate;

class CreateCalibrationWorksheet extends CreateRecord
{
    use HasAutosaveForCreate;

    protected static string $resource = CalibrationWorksheetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        $data['technician_name'] = $user?->name ?? ($data['technician_name'] ?? '—');

        // Tier 1: the worksheet pins the template it was built from so a later
        // template edit cannot retroactively change an issued certificate.
        $template = app(TemplateRegistry::class)->resolve($data['template_key']);
        $data['template_version'] = $template->version();
        $data['engine_version'] = CalibrationWorksheetResource::ENGINE_VERSION;

        return $data;
    }

    protected function afterCreate(): void
    {
        app(WorksheetHydrator::class)->hydrate($this->record);
    }

    protected function getRedirectUrl(): string
    {
        // Children only exist after the record is created, so send the
        // technician into the full edit form to enter measurements.
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
