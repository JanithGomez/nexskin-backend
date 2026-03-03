<?php

namespace App\Filament\Exports;

use App\Models\Order;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\ExportColumn;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('order_number')
                ->label('Order #'),

            ExportColumn::make('customer_name')
                ->label('Customer')
                ->state(fn (Order $record) => $record->user?->name ?? 'Guest'),

            ExportColumn::make('customer_email')
                ->label('Email')
                ->state(fn (Order $record) => $record->user?->email ?? '-'),

            ExportColumn::make('status'),

            ExportColumn::make('payment_status')
                ->label('Payment'),

            ExportColumn::make('total_amount')
                ->label('Total (LKR)'),

            ExportColumn::make('created_at')
                ->label('Created At')
                ->formatStateUsing(fn ($state) => $state?->format('Y-m-d H:i')),
        ];
    }

    /**
     * REQUIRED in Filament 3.2+
     */
    public static function getCompletedNotificationBody($export): string
    {
        $count = number_format($export->successful_rows);

        return "Your order export has completed successfully. {$count} row(s) exported.";
    }
}