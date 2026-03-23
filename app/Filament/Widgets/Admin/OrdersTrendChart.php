<?php

namespace App\Filament\Widgets\Admin;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class OrdersTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
    'default' => 1,
    'md' => 2,
    'xl' => 6,
    ];

    public function getHeading(): string
    {
        $days = $this->days();
        return "Orders trend ({$days} days)";
    }

    public function getDescription(): ?string
    {
        $days = $this->days();

        [$start, $end] = $this->currentRange($days);
        [$prevStart, $prevEnd] = $this->previousRange($start, $days);

        $current = (int) Order::query()->whereBetween('created_at', [$start, $end])->count();
        $previous = (int) Order::query()->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        if ($previous <= 0) {
            return "Current: {$current} • Prev {$days}d: {$previous} • Growth: —";
        }

        $pct = (($current - $previous) / $previous) * 100.0;
        $arrow = $pct >= 0 ? '↑' : '↓';
        $sign = $pct >= 0 ? '+' : '';
        $pctText = $sign . number_format($pct, 1) . '%';

        return "Current: {$current} • Prev {$days}d: {$previous} • {$arrow} {$pctText}";
    }

    protected function getData(): array
    {
        $days = $this->days();

        [$start, $end] = $this->currentRange($days);
        [$prevStart, $prevEnd] = $this->previousRange($start, $days);

        $currentSeries = $this->dailyCountSeries($start, $end);
        $previousSeries = $this->dailyCountSeries($prevStart, $prevEnd);

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
            'labels' => $currentSeries['labels'],
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

    private function dailyCountSeries($start, $end): array
    {
        $rows = Order::query()
            ->selectRaw('DATE(created_at) as d, COUNT(*) as cnt')
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
            $values[] = (int) ($rows[$key]->cnt ?? 0);
            $cursor->addDay();
        }

        return compact('labels', 'values');
    }
}