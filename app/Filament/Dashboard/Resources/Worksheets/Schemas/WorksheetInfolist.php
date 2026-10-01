<?php

namespace App\Filament\Dashboard\Resources\Worksheets\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorksheetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Berkas')
                    ->schema([
                        TextEntry::make('original_filename')
                            ->label('Nama Berkas'),
                        TextEntry::make('device_serial')
                            ->label('Nomor Seri'),
                        TextEntry::make('customer_name')
                            ->label('Faskes'),
                        TextEntry::make('calibration_date')
                            ->label('Tanggal Kalibrasi')
                            ->date('d/m/Y'),
                        TextEntry::make('conclusion')
                            ->label('Kesimpulan')
                            ->badge(),
                        TextEntry::make('cert_number')
                            ->label('Nomor Sertifikat'),
                        TextEntry::make('order_number')
                            ->label('Nomor Pesanan'),
                    ])->columns(3),

                Section::make('Identitas Alat')
                    ->statePath('payload.device')
                    ->schema([
                        TextEntry::make('name')->label('Nama Alat'),
                        TextEntry::make('brand')->label('Merk'),
                        TextEntry::make('type')->label('Tipe'),
                        TextEntry::make('serial')->label('Nomor Seri'),
                    ])->columns(4),

                Section::make('Pelaksanaan Kalibrasi')
                    ->statePath('payload.calibration')
                    ->schema([
                        TextEntry::make('room')->label('Ruangan Kalibrasi'),
                        TextEntry::make('technician')->label('Pelaksana Teknis'),
                        TextEntry::make('received_date')->label('Tanggal Penerimaan'),
                        TextEntry::make('calibration_date')->label('Tanggal Kalibrasi'),
                    ])->columns(4),

                Section::make('Kondisi Lingkungan')
                    ->schema([
                        TextEntry::make('payload.environment.temperature.value')
                            ->label('Suhu')
                            ->suffix(' °C'),
                        TextEntry::make('payload.environment.temperature.uncert')
                            ->label('Ketidakpastian Suhu')
                            ->prefix('± ')
                            ->suffix(' °C'),
                        TextEntry::make('payload.environment.humidity.value')
                            ->label('Kelembapan')
                            ->suffix(' % RH'),
                        TextEntry::make('payload.environment.main_voltage')
                            ->label('Tegangan Utama')
                            ->suffix(' Vac'),
                    ])->columns(4),

                Section::make('Daftar Alat yang Digunakan')
                    ->schema([
                        RepeatableEntry::make('payload.instruments')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('name')->label('Nama Alat'),
                                TextEntry::make('brand')->label('Merk'),
                                TextEntry::make('type')->label('Tipe'),
                                TextEntry::make('serial')->label('No. Seri'),
                                TextEntry::make('traceability')->label('Ketertelusuran'),
                            ])->columns(5),
                    ]),

                Section::make('Pemeriksaan Kondisi Fisik dan Fungsi')
                    ->schema([
                        RepeatableEntry::make('payload.physical')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('parameter')->label('Parameter'),
                                TextEntry::make('result')->label('Hasil'),
                            ])->columns(2),
                    ]),

                Section::make('Pengukuran Keselamatan Listrik')
                    ->schema([
                        RepeatableEntry::make('payload.electrical')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('parameter')->label('Parameter'),
                                TextEntry::make('measured')->label('Terukur'),
                                TextEntry::make('unit')->label('Satuan'),
                                TextEntry::make('limit')->label('Ambang Batas'),
                            ])->columns(4),
                    ]),

                Section::make('Keterangan')
                    ->schema([
                        RepeatableEntry::make('payload.notes')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('text')->hiddenLabel(),
                            ]),
                    ]),
            ]);
    }
}
