<?php

namespace App\Filament\Dashboard\Resources\Worksheets\Schemas;

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
            ]);
    }
}
