<?php

namespace App\Filament\Dashboard\Resources\CalibrationWorksheets\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
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
                TextColumn::make('total_score')
                    ->label('Skor')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
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
            ->recordActions([
                ViewAction::make(),
                Action::make('recalculate')
                    ->label('Hitung Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->recalculate()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
