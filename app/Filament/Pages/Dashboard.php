<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title = 'Admin Dashboard';

    public function getWidgets(): array
    {
        return [
            // \App\Filament\Widgets\StatsOverview::class,
             \App\Filament\Widgets\EcomStatsOverview::class,
              \App\Filament\Widgets\LowStockProductsTable::class,
            \App\Filament\Widgets\RevenueChart::class,
            //  \App\Filament\Widgets\TopProductsSoldTable::class,
            \App\Filament\Widgets\PaymentStatusChart::class,
            \App\Filament\Widgets\SalesLast14DaysChart::class,
            \App\Filament\Widgets\LatestOrdersTable::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        // Controls widget grid columns (e.g. 1, 2, 3, 4, etc.)
        return 3;
    }
}