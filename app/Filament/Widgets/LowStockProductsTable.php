<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockProductsTable extends BaseWidget
{
    protected int|string|array $columnSpan = 1;

    protected function getTableQuery(): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->where('stock', '<=', 5)
            ->orderBy('stock', 'asc');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')->limit(30)->searchable(),
            Tables\Columns\TextColumn::make('stock')->badge()->sortable(),
            Tables\Columns\TextColumn::make('price')->money('USD')->sortable(),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [5, 10, 25];
    }
}