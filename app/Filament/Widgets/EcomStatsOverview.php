<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EcomStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $startThisMonth = now()->startOfMonth();
        $startLastMonth = now()->subMonthNoOverflow()->startOfMonth();
        $endLastMonth   = now()->subMonthNoOverflow()->endOfMonth();

        // ✅ Revenue from PAYMENTS (real e-commerce)
        $revenueThisMonth = (float) Payment::where('status', 'paid')
            ->where('created_at', '>=', $startThisMonth)
            ->sum('amount');

        $revenueLastMonth = (float) Payment::where('status', 'paid')
            ->whereBetween('created_at', [$startLastMonth, $endLastMonth])
            ->sum('amount');

        $revDelta = $revenueLastMonth > 0
            ? (($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100
            : null;

        $ordersToday = Order::whereDate('created_at', today())->count();

        // ✅ Order statuses (change strings if your DB uses different text)
        $completedOrders = Order::where('status', 'completed')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();

        $pendingFulfillment = Order::whereIn('status', ['pending', 'processing'])->count();

        $paidOrders = Order::where('payment_status', 'paid')->count();
        $unpaidOrders = Order::where('payment_status', 'unpaid')->count();

        $lowStock = Product::where('stock', '<=', 5)->count();

        // ✅ Shipment health
        $shipmentsPending = Shipment::where('status', 'pending')->count();
        $deliveryAttemptsGt0 = Shipment::where('delivery_attempts', '>', 0)->count();

        return [
            Stat::make('Revenue (This Month)', 'LKR ' . number_format($revenueThisMonth, 2))
                ->description($revDelta === null ? 'No data last month' : (round($revDelta, 1) . '% vs last month'))
                ->descriptionIcon(
                    $revDelta !== null && $revDelta >= 0
                        ? 'heroicon-m-arrow-trending-up'
                        : 'heroicon-m-arrow-trending-down'
                ),

            Stat::make('Orders Today', $ordersToday),

            Stat::make('Completed Orders', $completedOrders),

            Stat::make('Cancelled Orders', $cancelledOrders),

            Stat::make('Pending Fulfillment', $pendingFulfillment)
                ->description('Orders to ship'),

            Stat::make('Paid / Unpaid', "{$paidOrders} / {$unpaidOrders}")
                ->description('Payment status'),

            Stat::make('Shipment Pending', $shipmentsPending),

            Stat::make('Delivery Attempts > 0', $deliveryAttemptsGt0),

            Stat::make('Low Stock (≤ 5)', $lowStock)
                ->description('Products need restock'),
        ];
    }
}