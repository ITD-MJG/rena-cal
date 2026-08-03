<?php

namespace App\Filament\Dashboard\Resources\CalibrationWorksheets\Tables;

use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CalibrationWorksheetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('device.device_number')
                    ->label('Nomor Alat')
                    ->searchable(),
                TextColumn::make('device.deviceName.name')
                    ->label('Nama Alat')
                    ->searchable(),
                TextColumn::make('service.name')
                    ->label('Pelayanan')
                    ->searchable(),
                TextColumn::make('calibration_date')
                    ->label('Tanggal Kalibrasi')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('technician_name')
                    ->label('Teknisi')
                    ->searchable(),
                TextColumn::make('conclusion')
                    ->label('Kesimpulan')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Laik Pakai' => 'success',
                        'Tidak Laik Pakai' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
