<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestOrdersTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return Order::query()->latest();
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('order_number')
                ->label('Order')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('customer')
                ->label('Customer')
                ->state(fn (Order $record) => $record->user?->name ?? $record->guest_name ?? 'Guest')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where('guest_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                }),

            Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            Tables\Columns\TextColumn::make('payment_status')->badge()->sortable(),

            Tables\Columns\TextColumn::make('total_amount')
                ->label('Total')
                ->money('USD')
                ->sortable(),

            Tables\Columns\TextColumn::make('created_at')->since()->label('Placed'),
        ];
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [5, 10, 25];
    }
}