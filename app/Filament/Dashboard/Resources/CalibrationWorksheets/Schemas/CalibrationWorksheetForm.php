<?php

namespace App\Filament\Dashboard\Resources\CalibrationWorksheets\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CalibrationWorksheetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    // ─── Step 1: Administrasi ───
                    Wizard\Step::make('Administrasi')
                        ->icon(Heroicon::ClipboardDocumentCheck)
                        ->schema([
                            Select::make('device_id')
                                ->label('Device')
                                ->relationship('device', 'device_number')
                                ->searchable()
                                ->preload()
                                ->getOptionLabelFromRecordUsing(
                                    fn ($record) => "{$record->device_number} — {$record->deviceName?->name} ({$record->brand?->name})"
                                )
                                ->required(),
                            Select::make('service_id')
                                ->label('Nama Pelayanan')
                                ->relationship('service', 'name')
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')->required(),
                                ])
                                ->visible(fn () => auth()->user()->hasRole(['Super Admin', 'Admin']))
                                ->required(),
                            Select::make('service_id')
                                ->label('Nama Pelayanan')
                                ->relationship('service', 'name')
                                ->searchable()
                                ->preload()
                                ->visible(fn () => ! auth()->user()->hasRole(['Super Admin', 'Admin']))
                                ->required(),
                            TextInput::make('calibration_room')
                                ->label('Ruangan Kalibrasi')
                                ->required(),
                            Grid::make(2)->schema([
                                DatePicker::make('received_date')
                                    ->label('Tanggal Penerimaan')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->format('Y-m-d')
                                    ->closeOnDateSelection()
                                    ->required(),
                                DatePicker::make('calibration_date')
                                    ->label('Tanggal Kalibrasi')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->format('Y-m-d')
                                    ->closeOnDateSelection()
                                    ->required(),
                            ]),
                            TextInput::make('work_method')
                                ->label('Metode Kerja')
                                ->placeholder('RKS/MT/IK.01-010'),
                            Placeholder::make('technician_name')
                                ->label('Pelaksana Teknis')
                                ->content(fn () => auth()->user()->name),
                        ]),

                    // ─── Step 2: Alat Ukur ───
                    Wizard\Step::make('Alat Ukur')
                        ->icon(Heroicon::WrenchScrewdriver)
                        ->schema([
                            Repeater::make('instruments')
                                ->relationship()
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Nama Alat')
                                        ->required(),
                                    Grid::make(3)->schema([
                                        TextInput::make('brand')
                                            ->label('Merk'),
                                        TextInput::make('type')
                                            ->label('Tipe'),
                                        TextInput::make('serial_number')
                                            ->label('No. Seri'),
                                    ]),
                                    TextInput::make('traceability')
                                        ->label('Ketertelusuran'),
                                ])
                                ->columns(1)
                                ->defaultItems(1)
                                ->addActionLabel('Tambah Alat'),
                        ]),

                    // ─── Step 3: Kondisi Lingkungan ───
                    Wizard\Step::make('Kondisi Lingkungan')
                        ->icon(Heroicon::Thermometer)
                        ->schema([
                            Section::make('Suhu dan Kelembaban')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('temperature_start')
                                            ->label('Suhu Awal (°C)')
                                            ->numeric()
                                            ->step(0.1),
                                        TextInput::make('temperature_end')
                                            ->label('Suhu Akhir (°C)')
                                            ->numeric()
                                            ->step(0.1),
                                    ]),
                                    Grid::make(2)->schema([
                                        TextInput::make('humidity_start')
                                            ->label('Kelembaban Awal (%RH)')
                                            ->numeric()
                                            ->step(0.1),
                                        TextInput::make('humidity_end')
                                            ->label('Kelembaban Akhir (%RH)')
                                            ->numeric()
                                            ->step(0.1),
                                    ]),
                                ]),
                            Section::make('Tegangan')
                                ->schema([
                                    TextInput::make('main_voltage')
                                        ->label('Tegangan Utama (Vac)')
                                        ->numeric()
                                        ->step(0.1),
                                ]),
                        ]),

                    // ─── Step 4: Pemeriksaan Fisik ───
                    Wizard\Step::make('Pemeriksaan Fisik')
                        ->icon(Heroicon::Eye)
                        ->schema([
                            Repeater::make('physicalInspections')
                                ->relationship()
                                ->schema([
                                    TextInput::make('parameter_name')
                                        ->label('Parameter')
                                        ->disabled()
                                        ->dehydrated(),
                                    TextInput::make('description')
                                        ->label('Deskripsi')
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpanFull(),
                                    Toggle::make('result')
                                        ->label('Baik')
                                        ->inline(false)
                                        ->onColor('success')
                                        ->offColor('danger')
                                        ->icons(true),
                                ])
                                ->columns(1)
                                ->disabled(),
                            Placeholder::make('physical_inspection_note')
                                ->content('Pemeriksaan fisik bersifat standar. Toggle result untuk setiap parameter.'),
                        ]),

                    // ─── Step 5: Keselamatan Listrik ───
                    Wizard\Step::make('Keselamatan Listrik')
                        ->icon(Heroicon::Bolt)
                        ->schema([
                            Repeater::make('electricalSafetyTests')
                                ->relationship()
                                ->schema([
                                    TextInput::make('parameter_name')
                                        ->label('Parameter')
                                        ->disabled()
                                        ->dehydrated(),
                                    Grid::make(3)->schema([
                                        TextInput::make('measurement_method')
                                            ->label('Metode')
                                            ->disabled()
                                            ->dehydrated(),
                                        TextInput::make('threshold_operator')
                                            ->label('Operator')
                                            ->disabled()
                                            ->dehydrated(),
                                        TextInput::make('threshold_value')
                                            ->label('Ambang Batas')
                                            ->disabled()
                                            ->dehydrated(),
                                    ]),
                                    Grid::make(2)->schema([
                                        TextInput::make('raw_value')
                                            ->label('Nilai Terukur')
                                            ->numeric()
                                            ->step(0.001)
                                            ->required(),
                                        TextInput::make('unit')
                                            ->label('Satuan')
                                            ->disabled()
                                            ->dehydrated(),
                                    ]),
                                ])
                                ->columns(1)
                                ->disabled(),
                        ]),

                    // ─── Step 6: Pengukuran Kinerja ───
                    Wizard\Step::make('Pengukuran Kinerja')
                        ->icon(Heroicon::ChartBar)
                        ->schema([
                            Repeater::make('performanceMeasurements')
                                ->relationship()
                                ->schema([
                                    TextInput::make('parameter_name')
                                        ->label('Parameter')
                                        ->disabled()
                                        ->dehydrated(),
                                    Grid::make(3)->schema([
                                        TextInput::make('setting_value')
                                            ->label('Setting Alat')
                                            ->disabled()
                                            ->dehydrated(),
                                        TextInput::make('setting_unit')
                                            ->label('Satuan')
                                            ->disabled()
                                            ->dehydrated(),
                                        TextInput::make('tolerance')
                                            ->label('Toleransi')
                                            ->disabled()
                                            ->dehydrated(),
                                    ]),
                                    Grid::make(3)->schema([
                                        TextInput::make('measurement_1')
                                            ->label('Pembacaan 1')
                                            ->numeric()
                                            ->step(0.01)
                                            ->required(),
                                        TextInput::make('measurement_2')
                                            ->label('Pembacaan 2')
                                            ->numeric()
                                            ->step(0.01)
                                            ->required(),
                                        TextInput::make('measurement_3')
                                            ->label('Pembacaan 3')
                                            ->numeric()
                                            ->step(0.01)
                                            ->required(),
                                    ]),
                                ])
                                ->columns(1),
                        ]),

                    // ─── Step 7: Kesimpulan ───
                    Wizard\Step::make('Kesimpulan')
                        ->icon(Heroicon::CheckCircle)
                        ->schema([
                            Placeholder::make('total_score')
                                ->label('Total Skor')
                                ->content(fn ($record) => $record?->computeTotalScore() ?? '—'),
                            Placeholder::make('physical_score')
                                ->label('Skor Pemeriksaan Fisik')
                                ->content(fn ($record) => $record?->physical_inspection_score.' / 60' ?? '—'),
                            Placeholder::make('electrical_score')
                                ->label('Skor Keselamatan Listrik')
                                ->content(fn ($record) => $record?->electrical_safety_score.' / 60' ?? '—'),
                            Placeholder::make('performance_score')
                                ->label('Skor Pengukuran Kinerja')
                                ->content(fn ($record) => $record?->performance_score.' / 100' ?? '—'),
                            Select::make('conclusion')
                                ->label('Kesimpulan')
                                ->options([
                                    'Laik Pakai' => 'Laik Pakai',
                                    'Tidak Laik Pakai' => 'Tidak Laik Pakai',
                                ])
                                ->default(fn ($record) => $record?->computeConclusion()),
                        ]),
                ])
                    ->skippable()
                    ->persistStepInQueryString()
                    ->submitAction(view('filament::components.wizard.submit-button')),
            ]);
    }
}
