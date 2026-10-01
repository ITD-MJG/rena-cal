<?php

namespace App\Filament\Dashboard\Resources\Worksheets;

use App\Filament\Dashboard\Resources\Worksheets\Pages\CreateWorksheet;
use App\Filament\Dashboard\Resources\Worksheets\Pages\EditWorksheet;
use App\Filament\Dashboard\Resources\Worksheets\Pages\ListWorksheets;
use App\Filament\Dashboard\Resources\Worksheets\Pages\ViewWorksheet;
use App\Filament\Dashboard\Resources\Worksheets\Schemas\WorksheetForm;
use App\Filament\Dashboard\Resources\Worksheets\Schemas\WorksheetInfolist;
use App\Filament\Dashboard\Resources\Worksheets\Tables\WorksheetsTable;
use App\Models\Worksheet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorksheetResource extends Resource
{
    protected static ?string $model = Worksheet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentArrowUp;

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
        return WorksheetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorksheetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorksheetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorksheets::route('/'),
            'create' => CreateWorksheet::route('/create'),
            'view' => ViewWorksheet::route('/{record}'),
            'edit' => EditWorksheet::route('/{record}/edit'),
        ];
    }
}
