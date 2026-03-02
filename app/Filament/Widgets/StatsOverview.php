<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
     {
            $revenue = (float) Payment::where('status', 'paid')->sum('amount');

        return [
            Stat::make('Users', User::count()),
            Stat::make('Orders', Order::count()),
            Stat::make('Revenue', number_format($revenue, 2) . ' LKR'),
        ];
    }
}