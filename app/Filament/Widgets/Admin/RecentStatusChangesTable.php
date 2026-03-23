<?php

namespace App\Filament\Widgets\Admin;

use App\Filament\Resources\OrderResource;
use App\Models\OrderStatusHistory;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentStatusChangesTable extends TableWidget
{
    protected static ?string $heading = 'Recent activity';
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
    'default' => 1,
    'md' => 2,
    'xl' => 6,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderStatusHistory::query()
                    ->with(['order:id,order_number', 'changer:id,name'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since(),

                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'order_status' => 'primary',
                        'payment_status' => 'warning',
                        'shipment_status' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'order_status' => 'Order',
                        'payment_status' => 'Payment',
                        'shipment_status' => 'Shipment',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('from_to')
                    ->label('Change')
                    ->state(function (OrderStatusHistory $record) {
                        $from = $record->from_status ? strtoupper($record->from_status) : '—';
                        $to = strtoupper($record->to_status);
                        return "{$from} → {$to}";
                    }),

                Tables\Columns\TextColumn::make('changer.name')
                    ->label('By')
                    ->placeholder('System')
                    ->limit(14),
            ])
            ->actions([
                Tables\Actions\Action::make('view_order')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (OrderStatusHistory $record) => $record->order
                        ? OrderResource::getUrl('view', ['record' => $record->order])
                        : null
                    )
                    ->visible(fn (OrderStatusHistory $record) => (bool) $record->order)
                    ->openUrlInNewTab(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}