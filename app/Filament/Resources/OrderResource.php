<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Mail\OrderUpdateMail;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    /**
     * ✅ Eager load to avoid N+1 and improve performance
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'user',
                'addresses',
                'items.product',
                'statusHistories.changer',
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->icon('heroicon-o-user')
                    ->state(function (Order $record) {
                        if ($record->user) return $record->user->name;

                        $billing = $record->addresses->firstWhere('type', 'billing');
                        return $billing?->name ?: 'Guest';
                    })
                    ->description(function (Order $record) {
                        $email = $record->user?->email
                            ?: $record->addresses->firstWhere('type', 'billing')?->email;

                        return $email ?: null;
                    })
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('addresses', fn ($q) =>
                                $q->where('type', 'billing')->where('name', 'like', "%{$search}%")
                            );
                    }),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('LKR')
                    ->sortable()
                    ->icon('heroicon-o-banknotes'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Order Status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state) => match ($state) {
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
                    ->sortable()
                    ->color(fn (string $state) => match ($state) {
                        'unpaid' => 'danger',
                        'paid' => 'success',
                        'refunded' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Placed')
                    ->dateTime()
                    ->sortable()
                    ->since(), // shows "2 hours ago" style text
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'processing' => 'Processing',
                    'shipped' => 'Shipped',
                    'delivered' => 'Delivered',
                    'cancelled' => 'Cancelled',
                ]),
                Tables\Filters\SelectFilter::make('payment_status')->options([
                    'unpaid' => 'Unpaid',
                    'paid' => 'Paid',
                    'refunded' => 'Refunded',
                    'failed' => 'Failed',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\ActionGroup::make([
                    // ✅ Notes
                    Tables\Actions\Action::make('edit_notes')
                        ->label('Admin Notes')
                        ->icon('heroicon-o-pencil-square')
                        ->form([
                            Forms\Components\Textarea::make('admin_notes')
                                ->label('Admin Notes')
                                ->rows(6),
                        ])
                        ->fillForm(fn (Order $record) => [
                            'admin_notes' => $record->admin_notes,
                        ])
                        ->action(fn (Order $record, array $data) => $record->update([
                            'admin_notes' => $data['admin_notes'] ?? null,
                        ])),

                    // ✅ Order status actions
                    self::orderStatusAction('mark_processing', 'Mark Processing', 'processing', ['pending'], 'info'),
                    self::orderStatusAction('mark_shipped', 'Mark Shipped', 'shipped', ['processing'], 'primary'),
                    self::orderStatusAction('mark_delivered', 'Mark Delivered', 'delivered', ['shipped'], 'success'),

                    Tables\Actions\Action::make('cancel_order')
                        ->label('Cancel Order')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel this order?')
                        ->modalDescription('This will record a timeline entry and notify the customer by email (if possible).')
                        ->form([
                            Forms\Components\Textarea::make('note')->label('Optional note')->rows(3),
                        ])
                        ->visible(fn (Order $record) => in_array($record->status, ['pending', 'processing'], true))
                        ->action(fn (Order $record, array $data) =>
                            self::updateOrderStatusAndNotify($record, 'cancelled', $data['note'] ?? null)
                        ),

                    // ✅ Payment status actions (confirm + note + disabled when cancelled)
                    self::paymentAction('mark_paid', 'Mark Paid', 'paid', 'success'),
                    self::paymentAction('mark_unpaid', 'Mark Unpaid', 'unpaid', 'warning'),
                    self::paymentAction('mark_refunded', 'Mark Refunded', 'refunded', 'gray'),
                ])
                    ->label('More')
                    ->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function orderStatusAction(
        string $name,
        string $label,
        string $toStatus,
        array $allowedFrom,
        string $color = 'primary'
    ) {
        return Tables\Actions\Action::make($name)
            ->label($label)
            ->color($color)
            ->icon('heroicon-o-arrow-right-circle')
            ->requiresConfirmation()
            ->modalHeading($label . '?')
            ->modalDescription('This will record a timeline entry and notify the customer by email (if possible).')
            ->form([
                Forms\Components\Textarea::make('note')->label('Optional note')->rows(3),
            ])
            ->visible(fn (Order $record) => in_array($record->status, $allowedFrom, true))
            ->action(fn (Order $record, array $data) =>
                self::updateOrderStatusAndNotify($record, $toStatus, $data['note'] ?? null)
            );
    }

    private static function paymentAction(string $name, string $label, string $toStatus, string $color)
    {
        return Tables\Actions\Action::make($name)
            ->label($label)
            ->icon('heroicon-o-banknotes')
            ->color($color)
            ->requiresConfirmation()
            ->modalHeading($label . '?')
            ->modalDescription('This will record a timeline entry and notify the customer by email (if possible).')
            ->form([
                Forms\Components\Textarea::make('note')->label('Optional note')->rows(3),
            ])
            ->visible(fn (Order $record) =>
                $record->payment_status !== $toStatus && $record->status !== 'cancelled'
            )
            ->action(fn (Order $record, array $data) =>
                self::updatePaymentStatusAndNotify($record, $toStatus, $data['note'] ?? null)
            );
    }

    // private static function updateOrderStatusAndNotify(Order $order, string $newStatus, ?string $note = null): void
    public static function updateOrderStatusAndNotify(Order $order, string $newStatus, ?string $note = null): void
    {
        $old = $order->status;
        if ($old === $newStatus) return;

        $order->update(['status' => $newStatus]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'type' => 'order_status',
            'from_status' => $old,
            'to_status' => $newStatus,
            'changed_by' => auth()->id(),
            'note' => $note,
        ]);

        self::sendOrderUpdateEmail($order, 'order_status', $old, $newStatus, $note);
    }

    // private static function updatePaymentStatusAndNotify(Order $order, string $newStatus, ?string $note = null): void
    public static function updatePaymentStatusAndNotify(Order $order, string $newStatus, ?string $note = null): void
    {
        $old = $order->payment_status;
        if ($old === $newStatus) return;

        $order->update(['payment_status' => $newStatus]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'type' => 'payment_status',
            'from_status' => $old,
            'to_status' => $newStatus,
            'changed_by' => auth()->id(),
            'note' => $note,
        ]);

        self::sendOrderUpdateEmail($order, 'payment_status', $old, $newStatus, $note);
    }

    private static function sendOrderUpdateEmail(Order $order, string $type, ?string $old, string $new, ?string $note): void
    {
        $email = $order->user?->email;

        if (! $email) {
            $billing = $order->addresses->firstWhere('type', 'billing');
            $email = $billing?->email;
        }

        if (! $email) return;

        try {
            Mail::to($email)->send(new OrderUpdateMail($order, $type, $old, $new, $note));
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Email failed to send')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isAdmin();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}