<?php

// namespace App\Filament\Pages;

// use Filament\Pages\Dashboard as BaseDashboard;

// class Dashboard extends BaseDashboard
// {
//     public function getColumns(): int|array
//     {
//         return 12;
//     }

//     public function getWidgets(): array
//     {

//         return [
//             \App\Filament\Widgets\Admin\KpiStatsOverview::class,

//             \App\Filament\Widgets\Admin\RevenueTrendChart::class,
//             \App\Filament\Widgets\Admin\OrdersTrendChart::class,

//             \App\Filament\Widgets\Admin\OrdersNeedingAttentionTable::class,
//             \App\Filament\Widgets\Admin\LowStockProductsTable::class,

//             \App\Filament\Widgets\Admin\PendingReviewsTable::class,
//             \App\Filament\Widgets\Admin\RecentStatusChangesTable::class,
//         ];
//     }
// }


namespace App\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function getColumns(): int|array
    {
        return 12;
    }

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Section::make('Filters')
                ->schema([
                    Select::make('range')
                        ->label('Date range')
                        ->options([
                            7 => 'Last 7 days',
                            30 => 'Last 30 days',
                            90 => 'Last 90 days',
                        ])
                        ->default(30)
                        ->selectablePlaceholder(false),
                ])
                ->columns(3)
                ->collapsed(),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\Admin\KpiStatsOverview::class,

            \App\Filament\Widgets\Admin\RevenueTrendChart::class,
            \App\Filament\Widgets\Admin\OrdersTrendChart::class,

            \App\Filament\Widgets\Admin\OrdersNeedingAttentionTable::class,
            \App\Filament\Widgets\Admin\LowStockProductsTable::class,

            \App\Filament\Widgets\Admin\PendingReviewsTable::class,
            \App\Filament\Widgets\Admin\RecentStatusChangesTable::class,
        ];
    }
}