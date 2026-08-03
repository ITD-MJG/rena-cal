<?php

namespace App\Filament\Dashboard\Resources\CalibrationWorksheets\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CalibrationWorksheetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ─── Administrasi ───
                Section::make('Administrasi')
                    ->schema([
                        TextEntry::make('device.device_number')
                            ->label('Nomor Alat'),
                        TextEntry::make('device.deviceName.name')
                            ->label('Nama Alat'),
                        TextEntry::make('device.brand.name')
                            ->label('Merk'),
                        TextEntry::make('service.name')
                            ->label('Nama Pelayanan'),
                        TextEntry::make('calibration_room')
                            ->label('Ruangan Kalibrasi'),
                        TextEntry::make('received_date')
                            ->label('Tanggal Penerimaan')
                            ->date('d/m/Y'),
                        TextEntry::make('calibration_date')
                            ->label('Tanggal Kalibrasi')
                            ->date('d/m/Y'),
                        TextEntry::make('technician_name')
                            ->label('Pelaksana Teknis'),
                        TextEntry::make('work_method')
                            ->label('Metode Kerja'),
                    ])->columns(3),

                // ─── Kondisi Lingkungan ───
                Section::make('Kondisi Lingkungan')
                    ->schema([
                        TextEntry::make('temperature_start')
                            ->label('Suhu Awal (°C)')
                            ->suffix('°C'),
                        TextEntry::make('temperature_end')
                            ->label('Suhu Akhir (°C)')
                            ->suffix('°C'),
                        TextEntry::make('humidity_start')
                            ->label('Kelembaban Awal (%RH)')
                            ->suffix('%RH'),
                        TextEntry::make('humidity_end')
                            ->label('Kelembaban Akhir (%RH)')
                            ->suffix('%RH'),
                        TextEntry::make('main_voltage')
                            ->label('Tegangan Utama')
                            ->suffix('Vac'),
                    ])->columns(3),

                // ─── Alat Ukur ───
                Section::make('Daftar Alat Ukur')
                    ->schema([
                        TextEntry::make('instruments')
                            ->label('Alat Ukur')
                            ->listWithLineBreaks()
                            ->formatStateUsing(fn ($state, $record) => $record->instruments->map(
                                fn ($i) => "{$i->name} ({$i->brand} {$i->type}) — {$i->serial_number}"
                            )->implode("\n")),
                    ]),

                // ─── Pemeriksaan Fisik ───
                Section::make('Pemeriksaan Fisik dan Fungsi Alat')
                    ->schema([
                        TextEntry::make('physicalInspections')
                            ->label('Hasil')
                            ->listWithLineBreaks()
                            ->formatStateUsing(fn ($state, $record) => $record->physicalInspections->map(
                                fn ($p) => "{$p->parameter_name}: ".($p->result ? 'Baik' : 'Tidak Baik')
                            )->implode("\n")),
                        TextEntry::make('physical_inspection_score')
                            ->label('Skor')
                            ->suffix(' / 60'),
                    ]),

                // ─── Keselamatan Listrik ───
                Section::make('Pengukuran Keselamatan Listrik')
                    ->schema([
                        TextEntry::make('electricalSafetyTests')
                            ->label('Hasil')
                            ->listWithLineBreaks()
                            ->formatStateUsing(fn ($state, $record) => $record->electricalSafetyTests->map(
                                fn ($t) => "{$t->parameter_name}: {$t->raw_value} {$t->unit} → {$t->result}"
                            )->implode("\n")),
                        TextEntry::make('electrical_safety_score')
                            ->label('Skor')
                            ->suffix(' / 60'),
                    ]),

                // ─── Pengukuran Kinerja ───
                Section::make('Pengukuran Kinerja')
                    ->schema([
                        TextEntry::make('performanceMeasurements')
                            ->label('Hasil')
                            ->listWithLineBreaks()
                            ->formatStateUsing(fn ($state, $record) => $record->performanceMeasurements->map(
                                fn ($m) => "{$m->parameter_name} ({$m->setting_value}{$m->setting_unit}): "
                                    ."{$m->measurement_1}, {$m->measurement_2}, {$m->measurement_3} → Mean: {$m->mean} → {$m->result}"
                            )->implode("\n")),
                        TextEntry::make('performance_score')
                            ->label('Skor')
                            ->suffix(' / 100'),
                    ]),

                // ─── Kesimpulan ───
                Section::make('Kesimpulan')
                    ->schema([
                        TextEntry::make('total_score')
                            ->label('Total Skor')
                            ->suffix(' / 220'),
                        IconEntry::make('conclusion')
                            ->label('Kesimpulan')
                            ->boolean()
                            ->trueIcon('heroicon-m-check-circle')
                            ->falseIcon('heroicon-m-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger')
                            ->formatStateUsing(fn ($state) => $state === 'Laik Pakai'),
                    ]),
            ]);
    }
}
