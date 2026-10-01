<?php

namespace App\Filament\Dashboard\Resources\Worksheets\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WorksheetForm
{
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
                    // Only a create needs the workbook. On edit the stored file is
                    // not repopulated into this field, so requiring it would make
                    // every save fail unless the user re-uploaded the workbook.
                    ->required(fn (string $operation) => $operation === 'create'),
                Select::make('device_id')
                    ->label('Device')
                    ->relationship('device', 'device_number')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(
                        fn ($record) => "{$record->device_number} — {$record->serial_number}"
                    ),
                TextInput::make('cert_number')
                    ->label('Nomor Sertifikat'),
                TextInput::make('order_number')
                    ->label('Nomor Pesanan'),
            ]);
    }
}
