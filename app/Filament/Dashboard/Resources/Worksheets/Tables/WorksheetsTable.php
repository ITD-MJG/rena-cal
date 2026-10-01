<?php

namespace App\Filament\Dashboard\Resources\Worksheets\Tables;

use App\Models\Worksheet;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorksheetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // A Hospital Admin only ever sees their own customer's worksheets.
            // viewAny is true for them, and Filament does not filter rows by the
            // view() policy, so the query has to be scoped here.
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();

                if ($user && $user->hasRole('Hospital Admin') && $user->customer_id) {
                    return $query->whereHas('device', function ($q) use ($user) {
                        $q->where('customer_id', $user->customer_id);
                    });
                }

                return $query;
            })
            ->columns([
                TextColumn::make('device_serial')
                    ->label('Nomor Seri')
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label('Faskes')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('calibration_date')
                    ->label('Tanggal Kalibrasi')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('conclusion')
                    ->label('Kesimpulan')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        Worksheet::CONCLUSION_LAIK => 'success',
                        Worksheet::CONCLUSION_TIDAK_LAIK => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Worksheet::STATUS_GENERATED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
