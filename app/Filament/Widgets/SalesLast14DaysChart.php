<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class SalesLast14DaysChart extends ChartWidget
{
    protected static ?string $heading = 'Sales (Last 14 days)';
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        $sales = Order::query()
            ->selectRaw('DATE(created_at) as day, SUM(total_amount) as revenue')
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('day')
            ->pluck('revenue', 'day');

        $data = $days->map(fn ($d) => (float) ($sales[$d] ?? 0))->toArray();
        $labels = $days->map(fn ($d) => date('M d', strtotime($d)))->toArray();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}