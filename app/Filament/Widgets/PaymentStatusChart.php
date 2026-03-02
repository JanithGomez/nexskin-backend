<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class PaymentStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Payment Status';

    protected function getData(): array
    {
        $paid = Order::where('payment_status', 'paid')->count();
        $unpaid = Order::where('payment_status', 'unpaid')->count();

        return [
            'labels' => ['Paid', 'Unpaid'],
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => [(int) $paid, (int) $unpaid],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}