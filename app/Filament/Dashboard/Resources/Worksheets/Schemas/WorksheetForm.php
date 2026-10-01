<?php

namespace App\Filament\Dashboard\Resources\Worksheets\Schemas;

use App\Models\Device;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WorksheetForm
{
    /**
     * Device code, then device name, then serial number. A blank name is
     * dropped rather than leaving a dangling separator.
     */
    public static function deviceOptionLabel(Device $device): string
    {
        return collect([
            $device->device_number,
            $device->deviceName?->name,
            $device->serial_number,
        ])->filter()->implode(' — ');
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file')
                    ->label('Berkas Lembar Kerja')
                    ->helperText('Unggah berkas Excel lembar kerja yang sudah terisi.')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->disk('public')
                    ->directory('worksheets')
                    ->preserveFilenames()
                    // Only a create needs the workbook. On edit the stored file is
                    // not repopulated into this field, so requiring it would make
                    // every save fail unless the user re-uploaded the workbook.
                    ->required(fn (string $operation) => $operation === 'create'),
                Select::make('device_id')
                    ->label('Device')
                    // Third argument eager loads the name, so building each
                    // option label does not fire a query per device.
                    ->relationship('device', 'device_number', fn ($query) => $query->with('deviceName'))
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn (Device $record) => self::deviceOptionLabel($record)),
                TextInput::make('cert_number')
                    ->label('Nomor Sertifikat'),
                TextInput::make('order_number')
                    ->label('Nomor Pesanan'),
            ]);
    }
}
