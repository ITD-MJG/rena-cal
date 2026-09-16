<?php

namespace App\Filament\Dashboard\Resources\CalibrationWorksheets\Schemas;

use App\Calibration\TemplateRegistry;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class CalibrationWorksheetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    // ─── Step 1: Administrasi ───
                    Step::make('Administrasi')
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
                            Select::make('template_key')
                                ->label('Jenis Alat')
                                ->options(fn () => app(TemplateRegistry::class)->options())
                                ->default('autoclave')
                                ->required()
                                ->live(),
                            Select::make('service_id')
                                ->label('Nama Pelayanan')
                                ->relationship('service', 'name')
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')->required(),
                                ])
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
                                ->content(function () {
                                    /** @var User|null $user */
                                    $user = Auth::user();

                                    return $user?->name ?? '—';
                                }),
                        ]),

                    // ─── Step 2: Alat Ukur ───
                    Step::make('Alat Ukur')
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
                            // Instruments may be entered during creation;
                            // the other repeaters are hydration-owned.
                            // (see dehydrated() below)
                        ]),

                    // ─── Step 3: Kondisi Lingkungan ───
                    Step::make('Kondisi Lingkungan')
                        ->icon(Heroicon::Fire)
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
                    Step::make('Pemeriksaan Fisik')
                        ->icon(Heroicon::Eye)
                        ->visible(fn ($record) => $record !== null)
                        ->schema([
                            Repeater::make('physicalInspections')
                                ->relationship()
                                ->dehydrated(fn ($record) => $record !== null)
                                // Rows are seeded by WorksheetHydrator after
                                // create, so the create form must not spawn a
                                // blank item (Filament's default is 1).
                                ->defaultItems(0)
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
                                        ->onIcon(Heroicon::CheckCircle)
                                        ->offIcon(Heroicon::XCircle),
                                ])
                                ->columns(1),
                            Placeholder::make('physical_inspection_note')
                                ->content('Pemeriksaan fisik bersifat standar. Toggle result untuk setiap parameter.'),
                        ]),

                    // ─── Step 5: Keselamatan Listrik ───
                    Step::make('Keselamatan Listrik')
                        ->icon(Heroicon::Bolt)
                        ->visible(fn ($record) => $record !== null)
                        ->schema([
                            Repeater::make('electricalSafetyTests')
                                ->relationship()
                                ->dehydrated(fn ($record) => $record !== null)
                                // Rows are seeded by WorksheetHydrator after
                                // create, so the create form must not spawn a
                                // blank item (Filament's default is 1).
                                ->defaultItems(0)
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
                                            ->helperText('Kosongkan bila parameter tidak diaplikasikan pada alat ini.'),
                                        TextInput::make('unit')
                                            ->label('Satuan')
                                            ->disabled()
                                            ->dehydrated(),
                                    ]),
                                ])
                                ->columns(1),
                        ]),

                    // ─── Step 6: Pengukuran Kinerja ───
                    Step::make('Pengukuran Kinerja')
                        ->icon(Heroicon::ChartBar)
                        ->visible(fn ($record) => $record !== null)
                        ->schema([
                            Repeater::make('performanceMeasurements')
                                ->relationship()
                                ->dehydrated(fn ($record) => $record !== null)
                                // Rows are seeded by WorksheetHydrator after
                                // create, so the create form must not spawn a
                                // blank item (Filament's default is 1).
                                ->defaultItems(0)
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
                                    Repeater::make('readings')
                                        ->relationship()
                                        ->label('Hasil Pengukuran')
                                        ->schema([
                                            TextInput::make('point_label')
                                                ->label('Pembacaan')
                                                ->disabled()
                                                ->dehydrated(),
                                            TextInput::make('value')
                                                ->label('Nilai')
                                                ->numeric()
                                                ->step(0.01),
                                        ])
                                        ->columns(2)
                                        ->addable(false)
                                        ->deletable(false)
                                        ->reorderable(false)
                                        ->visible(fn ($record) => $record !== null),
                                ])
                                ->columns(1),
                        ]),

                    // ─── Step 7: Kesimpulan ───
                    Step::make('Kesimpulan')
                        ->icon(Heroicon::CheckCircle)
                        ->visible(fn ($record) => $record !== null)
                        ->schema([
                            Placeholder::make('score_summary')
                                ->label('Rekapitulasi Skor')
                                ->content(function ($record) {
                                    if ($record === null) {
                                        return 'Simpan lembar kerja untuk menghitung skor.';
                                    }

                                    $snapshot = $record->calculation_snapshot ?? [];

                                    if ($snapshot === []) {
                                        return 'Belum dihitung.';
                                    }

                                    return sprintf(
                                        'Fisik %s/10 · Listrik %s/40 · Kinerja %s/50 · Total %s/100 (ambang %s)',
                                        $snapshot['physical_score'] ?? '—',
                                        $snapshot['electrical_score'] ?? '—',
                                        $snapshot['performance_score'] ?? '—',
                                        $snapshot['total_score'] ?? '—',
                                        $snapshot['threshold'] ?? '—',
                                    );
                                }),
                            Select::make('conclusion')
                                ->label('Kesimpulan')
                                ->options([
                                    'Laik Pakai' => 'Laik Pakai',
                                    'Tidak Laik Pakai' => 'Tidak Laik Pakai',
                                ])
                                ->disabled()
                                ->dehydrated()
                                ->default(fn ($record) => $record?->computeConclusion()),
                        ]),
                ])
                    ->skippable()
                    ->persistStepInQueryString()
                    ->submitAction('Simpan'),
            ]);
    }
}
