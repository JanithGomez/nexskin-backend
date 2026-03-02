<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TopProductsSoldTable extends BaseWidget
{
    protected static ?string $heading = 'Top Products Sold (Last 30 days)';
    protected int|string|array $columnSpan = 'full';

    // protected function getTableQuery(): Builder
    // {
        // $since = now()->subDays(30)->startOfDay();

        // return DB::table('order_items')
        //     ->join('orders', 'orders.id', '=', 'order_items.order_id')
        //     ->join('products', 'products.id', '=', 'order_items.product_id')
        //     ->where('orders.payment_status', '=', 'paid')
        //     ->where('orders.created_at', '>=', $since)
        //     ->groupBy('order_items.product_id', 'products.name')
        //     ->selectRaw('
        //         MIN(order_items.id) as id,
        //         order_items.product_id,
        //         products.name as product_name,
        //         SUM(order_items.quantity) as qty_sold,
        //         SUM(order_items.quantity * order_items.price) as revenue
        //     ')
        //     ->orderByDesc('qty_sold')
        //     ->limit(10);
    // }

    // protected function getTableColumns(): array
    // {
    //     return [
    //         Tables\Columns\TextColumn::make('product_name')
    //             ->label('Product')
    //             ->limit(40),

    //         Tables\Columns\TextColumn::make('qty_sold')
    //             ->label('Qty Sold'),

    //         Tables\Columns\TextColumn::make('revenue')
    //             ->label('Revenue')
    //             ->formatStateUsing(fn ($state) => 'LKR ' . number_format((float) $state, 2)),
    //     ];
    // }

    // protected function isTablePaginationEnabled(): bool
    // {
    //     return false;
    // }
}