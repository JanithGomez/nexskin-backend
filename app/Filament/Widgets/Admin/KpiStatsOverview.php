<?php

namespace App\Filament\Widgets\Admin;

use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KpiStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        [$start, $end, $days] = $this->currentRange();
        [$prevStart, $prevEnd] = $this->previousRange($start, $days);

        $revenueCurrent = (float) Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $revenuePrevious = (float) Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('total_amount');

        [$growthText, $growthColor, $growthIcon] = $this->growthMeta($revenueCurrent, $revenuePrevious, $days);

        $ordersCurrent = (int) Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $pendingPayments = (int) Order::query()
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'pending')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // operational: live (not range filtered)
        $toFulfill = (int) Order::query()
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        // inventory: live (not range filtered)
        $lowStockCount = (int) Product::query()
            ->where('is_active', true)
            ->where('stock', '<=', 5)
            ->count();

        return [
            Stat::make("Revenue ({$days}d)", number_format($revenueCurrent, 2) . ' LKR')
                ->icon('heroicon-o-banknotes')
                ->description($growthText)
                ->descriptionIcon($growthIcon)
                ->color($growthColor),

            Stat::make("Orders ({$days}d)", $ordersCurrent)
                ->icon('heroicon-o-receipt-refund')
                ->description("Created between {$start->format('M d')} and {$end->format('M d')}")
                ->color('primary'),

            Stat::make('Pending payments', $pendingPayments)
                ->icon('heroicon-o-credit-card')
                ->description("In selected range ({$days}d)")
                ->color($pendingPayments > 0 ? 'warning' : 'success'),

            Stat::make('To fulfill', $toFulfill)
                ->icon('heroicon-o-clipboard-document-check')
                ->description('Pending + Processing (live)')
                ->color($toFulfill > 0 ? 'info' : 'success'),

            Stat::make('Low stock', $lowStockCount)
                ->icon('heroicon-o-exclamation-triangle')
                ->description('Active products ≤ 5 (live)')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),
        ];
    }

    private function currentRange(): array
    {
        $days = (int) ($this->filters['range'] ?? 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $end = now()->endOfDay();
        $start = now()->subDays($days - 1)->startOfDay();

        return [$start, $end, $days];
    }

    private function previousRange($currentStart, int $days): array
    {
        $prevEnd = (clone $currentStart)->subSecond(); // end of day before currentStart
        $prevStart = (clone $currentStart)->subDays($days)->startOfDay();

        return [$prevStart, $prevEnd];
    }

    private function growthMeta(float $current, float $previous, int $days): array
    {
        $prevText = number_format($previous, 2) . ' LKR';

        if ($previous <= 0) {
            return ["Prev {$days}d: {$prevText} • Growth: —", 'gray', 'heroicon-o-minus'];
        }

        $pct = (($current - $previous) / $previous) * 100.0;
        $sign = $pct >= 0 ? '+' : '';
        $pctText = $sign . number_format($pct, 1) . '%';

        $color = $pct >= 0 ? 'success' : 'danger';
        $icon = $pct >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down';

        return ["Prev {$days}d: {$prevText} • {$pctText}", $color, $icon];
    }
}