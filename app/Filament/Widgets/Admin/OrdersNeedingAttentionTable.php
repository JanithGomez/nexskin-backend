<?php

namespace App\Filament\Widgets\Admin;

use App\Filament\Exports\OrderExporter;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OrdersNeedingAttentionTable extends TableWidget
{
    protected static ?string $heading = 'Orders needing attention';
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = [
    'default' => 1,
    'md' => 2,
    'xl' => 6,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->weight('bold')
                    ->searchable()
                    ->copyable(),

                // Tables\Columns\TextColumn::make('customer')
                //     ->label('Customer')
                //     ->state(fn (Order $record) => $record->user?->name ?: 'Guest')
                //     ->description(fn (Order $record) => $record->user?->email)
                //     ->limit(24),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('LKR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('attention_reason')
                    ->label('Reason')
                    ->badge()
                    ->color(function (Order $record) {
                        $attempts = (int) ($record->shipment?->delivery_attempts ?? 0);
                        $isShipped = $record->status === 'shipped';
                        $isNotDelivered = ($record->shipment?->status ?? null) !== 'delivered';

                        $shippedAt = $this->asCarbon($record->shipment?->shipped_at);

                        $isShippedTooLong = $isShipped
                            && $isNotDelivered
                            && $shippedAt
                            && $shippedAt->lte(now()->subDays(3));

                        return match (true) {
                            $record->payment_status === 'pending' => 'warning',
                            $attempts >= 2 => 'danger',
                            $isShippedTooLong => 'danger',
                            ($isShipped && $isNotDelivered) => 'info',
                            default => 'gray',
                        };
                    })
                    ->state(function (Order $record) {
                        $attempts = (int) ($record->shipment?->delivery_attempts ?? 0);
                        $isShipped = $record->status === 'shipped';
                        $isNotDelivered = ($record->shipment?->status ?? null) !== 'delivered';

                        $shippedAt = $this->asCarbon($record->shipment?->shipped_at);

                        $isShippedTooLong = $isShipped
                            && $isNotDelivered
                            && $shippedAt
                            && $shippedAt->lte(now()->subDays(3));

                        return match (true) {
                            $record->payment_status === 'pending' => 'Pending payment',
                            $attempts >= 2 => 'Delivery failing',
                            $isShippedTooLong => 'Shipped too long',
                            ($isShipped && $isNotDelivered) => 'Shipped (not delivered)',
                            default => '—',
                        };
                    }),

                Tables\Columns\TextColumn::make('shipment.delivery_attempts')
                    ->label('Attempts')
                    ->alignCenter()
                    ->state(fn (Order $record) => (int) ($record->shipment?->delivery_attempts ?? 0)),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export')
                    ->exporter(OrderExporter::class)
                    ->visible(fn () => auth()->user()?->role === 'admin'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }

    /**
     * Safely convert shipped_at into Carbon (handles Carbon|string|null).
     */
    private function asCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function query(): Builder
    {
        $threshold = now()->subDays(3);

        return Order::query()
            ->with(['user:id,name,email', 'shipment:id,order_id,delivery_attempts,status,shipped_at'])
            ->where('status', '!=', 'cancelled')
            ->where(function (Builder $q) use ($threshold) {
                $q->where('payment_status', 'pending')
                    ->orWhereHas('shipment', fn (Builder $s) => $s->where('delivery_attempts', '>=', 2))
                    ->orWhere(function (Builder $q2) use ($threshold) {
                        $q2->where('status', 'shipped')
                            ->whereHas('shipment', function (Builder $s) use ($threshold) {
                                $s->where('status', '!=', 'delivered')
                                    ->whereNotNull('shipped_at')
                                    ->where('shipped_at', '<=', $threshold);
                            });
                    })
                    ->orWhere(function (Builder $q3) {
                        $q3->where('status', 'shipped')
                            ->whereHas('shipment', fn (Builder $s) => $s->where('status', '!=', 'delivered'));
                    });
            });
    }
}