<?php

namespace App\Filament\Widgets\Admin;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class RevenueTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        $days = $this->days();
        return "Revenue trend ({$days} days)";
    }

    public function getDescription(): ?string
    {
        $days = $this->days();

        [$start, $end] = $this->currentRange($days);
        [$prevStart, $prevEnd] = $this->previousRange($start, $days);

        $current = (float) Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $previous = (float) Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('total_amount');

        return $this->growthText($current, $previous, $days, 'LKR');
    }

    protected function getData(): array
    {
        $days = $this->days();

        [$start, $end] = $this->currentRange($days);
        [$prevStart, $prevEnd] = $this->previousRange($start, $days);

        $currentSeries = $this->dailyRevenueSeries($start, $end);
        $previousSeries = $this->dailyRevenueSeries($prevStart, $prevEnd);

        return [
            'datasets' => [
                [
                    'label' => "Current {$days}d",
                    'data' => $currentSeries['values'],
                    'tension' => 0.25,
                    'fill' => false,
                ],
                [
                    'label' => "Previous {$days}d",
                    'data' => $previousSeries['values'],
                    'tension' => 0.25,
                    'fill' => false,
                    'borderDash' => [6, 6],
                ],
            ],
            'labels' => $currentSeries['labels'], // current labels
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function days(): int
    {
        $days = (int) ($this->filters['range'] ?? 30);
        return in_array($days, [7, 30, 90], true) ? $days : 30;
    }

    private function currentRange(int $days): array
    {
        $end = now()->endOfDay();
        $start = now()->subDays($days - 1)->startOfDay();
        return [$start, $end];
    }

    private function previousRange($currentStart, int $days): array
    {
        $prevEnd = (clone $currentStart)->subSecond();
        $prevStart = (clone $currentStart)->subDays($days)->startOfDay();
        return [$prevStart, $prevEnd];
    }

    private function dailyRevenueSeries($start, $end): array
    {
        $rows = Order::query()
            ->selectRaw('DATE(created_at) as d, SUM(total_amount) as total')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $labels = [];
        $values = [];

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('M d');
            $values[] = (float) ($rows[$key]->total ?? 0);
            $cursor->addDay();
        }

        return compact('labels', 'values');
    }

    private function growthText(float $current, float $previous, int $days, string $unit): string
    {
        $prevText = number_format($previous, 2) . " {$unit}";
        $currText = number_format($current, 2) . " {$unit}";

        if ($previous <= 0) {
            return "Current: {$currText} • Prev {$days}d: {$prevText} • Growth: —";
        }

        $pct = (($current - $previous) / $previous) * 100.0;
        $arrow = $pct >= 0 ? '↑' : '↓';
        $sign = $pct >= 0 ? '+' : '';
        $pctText = $sign . number_format($pct, 1) . '%';

        return "Current: {$currText} • Prev {$days}d: {$prevText} • {$arrow} {$pctText}";
    }
}