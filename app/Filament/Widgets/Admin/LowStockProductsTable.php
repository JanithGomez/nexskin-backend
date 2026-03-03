<?php

namespace App\Filament\Widgets\Admin;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStockProductsTable extends TableWidget
{
    protected static ?string $heading = 'Low stock products';
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 6;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    // ->with(['primaryImage'])
                    ->where('is_active', true)
                    ->where('stock', '<=', 5)
                    ->orderBy('stock')
            )
            ->columns([
                // Tables\Columns\IconColumn::make('primaryImage')
                //     ->label('Img')
                //     ->boolean()
                //     ->trueIcon('heroicon-o-photo')
                //     ->falseIcon('heroicon-o-x-mark')
                //     ->state(fn (Product $record) => (bool) $record->primaryImage),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->limit(28)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('stock')
                    ->badge()
                    ->color(fn ($state) => ((int) $state) <= 0 ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('price')
                    ->money('LKR'),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10);
    }
}