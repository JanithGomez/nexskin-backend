<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    /**
     * ✅ FAST QUERY for INDEX table:
     * - Keep relations minimal
     * - Use withCount / withSum instead of loading items
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('orders.*')
            ->with([
                'user:id,name,email',
                'shipment:id,order_id,tracking_number,delivery_attempts,status,carrier',
                'payment:id,order_id,payment_method',
                // NOTE: we do NOT load addresses/items/statusHistories here (heavy)
            ])
            ->withCount([
                'items as products_count', // number of order_items rows
            ])
            ->withSum('items as items_qty_sum', 'quantity'); // total qty
    }

    public static function table(Table $table): Table
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
                        // ✅ fast: use user if exists, else show Guest (avoid addresses on index)
                        return $record->user?->name ?: 'Guest';
                    })
                    ->description(function (Order $record) {
                        return $record->user?->email ?: null;
                    })
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                    }),

                Tables\Columns\TextColumn::make('items_qty_sum')
                    ->label('Items')
                    ->alignCenter()
                    ->state(fn (Order $record) => (int) ($record->items_qty_sum ?? 0))
                    ->sortable(query: function (Builder $query, string $direction) {
                        // withSum alias sorting (MySQL needs raw)
                        $query->orderByRaw('COALESCE(items_qty_sum, 0) ' . ($direction === 'desc' ? 'DESC' : 'ASC'));
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('LKR')
                    ->sortable()
                    ->icon('heroicon-o-banknotes'),

                // ✅ Tracking
                Tables\Columns\TextColumn::make('shipment.tracking_number')
                    ->label('Tracking')
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ Delivery attempts
                Tables\Columns\TextColumn::make('shipment.delivery_attempts')
                    ->label('Delivery Attempts')
                    ->placeholder('0')
                    ->alignCenter()
                    ->sortable(query: function (Builder $query, string $direction) {
                        $query->leftJoin('shipments', 'shipments.order_id', '=', 'orders.id')
                            ->orderBy('shipments.delivery_attempts', $direction)
                            ->select('orders.*');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Order Status')
                    ->badge()
                    ->sortable()
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
                    ->sortable()
                    ->color(fn (?string $state) => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Placed')
                    ->dateTime()
                    ->sortable()
                    ->since(),
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
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                    'failed' => 'Failed',
                    'refunded' => 'Refunded',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\ActionGroup::make([
                    // ✅ light edit notes action (doesn't require heavy relations)
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
                ])
                    ->label('More')
                    ->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
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