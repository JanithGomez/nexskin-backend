<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Revenue by Order Status';

    protected function getData(): array
    {
        $rows = Order::query()
            ->selectRaw('status, SUM(total_amount) as revenue')
            ->groupBy('status')
            ->orderByDesc('revenue')
            ->get();

        return [
            'labels' => $rows->pluck('status')->toArray(),
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $rows->pluck('revenue')->map(fn ($v) => (float) $v)->toArray(),
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}