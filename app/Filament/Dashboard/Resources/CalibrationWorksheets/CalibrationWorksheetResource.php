<?php

namespace App\Filament\Dashboard\Resources\CalibrationWorksheets;

use App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages\CreateCalibrationWorksheet;
use App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages\EditCalibrationWorksheet;
use App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages\ListCalibrationWorksheets;
use App\Filament\Dashboard\Resources\CalibrationWorksheets\Pages\ViewCalibrationWorksheet;
use App\Filament\Dashboard\Resources\CalibrationWorksheets\Schemas\CalibrationWorksheetForm;
use App\Filament\Dashboard\Resources\CalibrationWorksheets\Schemas\CalibrationWorksheetInfolist;
use App\Filament\Dashboard\Resources\CalibrationWorksheets\Tables\CalibrationWorksheetsTable;
use App\Models\CalibrationWorksheet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CalibrationWorksheetResource extends Resource
{
    /**
     * Bump when the calculation engine changes in a way that would alter a
     * previously issued score. Stored on each worksheet so old certificates
     * stay reproducible.
     */
    public const ENGINE_VERSION = '1.0.0';

    protected static ?string $model = CalibrationWorksheet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    public static function getModelLabel(): string
    {
        return 'Lembar Kerja';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Lembar Kerja';
    }

    public static function getNavigationLabel(): string
    {
        return 'Lembar Kerja';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Kalibrasi';
    }

    public static function form(Schema $schema): Schema
    {
        return CalibrationWorksheetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CalibrationWorksheetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CalibrationWorksheetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalibrationWorksheets::route('/'),
            'create' => CreateCalibrationWorksheet::route('/create'),
            'view' => ViewCalibrationWorksheet::route('/{record}'),
            'edit' => EditCalibrationWorksheet::route('/{record}/edit'),
        ];
    }
}
