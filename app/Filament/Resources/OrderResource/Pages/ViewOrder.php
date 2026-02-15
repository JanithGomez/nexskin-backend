<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Services\OrderEventService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * ✅ Header dropdowns:
     * - Order Status
     * - Payment Status (strict flow)
     * - Shipment (tracking created -> picked up -> delivered)
     */
    protected function getHeaderActions(): array
    {
        return [
            // =============================
            // ORDER STATUS DROPDOWN
            // =============================
            Actions\ActionGroup::make([
                $this->orderStatusHeaderAction(
                    name: 'mark_processing',
                    label: 'Mark Processing',
                    toStatus: 'processing',
                    allowedFrom: ['pending'],
                    color: 'info',
                ),
                $this->orderStatusHeaderAction(
                    name: 'mark_shipped',
                    label: 'Mark Shipped',
                    toStatus: 'shipped',
                    allowedFrom: ['processing'],
                    color: 'primary',
                ),
                $this->orderStatusHeaderAction(
                    name: 'mark_delivered',
                    label: 'Mark Delivered',
                    toStatus: 'delivered',
                    allowedFrom: ['shipped'],
                    color: 'success',
                ),

                Actions\Action::make('cancel_order')
                    ->label('Cancel Order')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel this order?')
                    ->modalDescription('This will record a timeline entry and notify the customer by email (if possible).')
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Optional note')
                            ->rows(3),
                    ])
                    ->visible(fn () => in_array($this->record->status, ['pending', 'processing'], true))
                    ->action(function (array $data) {
                        OrderEventService::orderStatus($this->record, 'cancelled', $data['note'] ?? null);
                        $this->record->refresh();
                    }),
            ])
                ->label('Order Status')
                ->icon('heroicon-o-arrows-right-left'),

            // =============================
            // PAYMENT STATUS DROPDOWN
            // ✅ strict flow: unpaid -> paid -> refunded
            // =============================
                Actions\ActionGroup::make([
                $this->paymentHeaderAction(
                    name: 'mark_paid',
                    label: 'Mark Paid',
                    toStatus: 'paid',
                    color: 'success',
                ),

                $this->paymentHeaderAction(
                    name: 'mark_failed',
                    label: 'Mark Failed',
                    toStatus: 'failed',
                    color: 'danger',
                ),

                $this->paymentHeaderAction(
                    name: 'mark_refunded',
                    label: 'Mark Refunded',
                    toStatus: 'refunded',
                    color: 'gray',
                ),
            ])
                ->label('Payment Status')
                ->icon('heroicon-o-banknotes'),

            // =============================
            // SHIPMENT DROPDOWN
            // ✅ tracking created -> picked up -> delivered
            // =============================
            Actions\ActionGroup::make([
                Actions\Action::make('shipment_tracking_created')
                    ->label('Add Tracking')
                    ->icon('heroicon-o-ticket')
                    ->color('primary')
                    ->modalHeading('Add shipment tracking')
                    ->modalDescription('Add tracking number & carrier. This will also create a timeline entry.')
                    ->form([
                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('carrier')
                            ->label('Carrier')
                            ->placeholder('Domex / Pronto / Koombiyo / ...')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('note')
                            ->label('Optional note')
                            ->rows(3),
                    ])
                    ->fillForm(function () {
                        $shipment = $this->record->shipment;
                        return [
                            'tracking_number' => $shipment?->tracking_number,
                            'carrier' => $shipment?->carrier,
                        ];
                    })
                    ->visible(function () {
                        if ($this->record->status === 'cancelled') return false;

                        $shipment = $this->record->shipment;
                        if (! $shipment) return true;

                        // show if no tracking exists yet
                        return empty($shipment->tracking_number);
                    })
                    ->action(function (array $data) {
                        OrderEventService::shipmentTrackingCreated(
                            order: $this->record,
                            trackingNumber: $data['tracking_number'],
                            carrier: $data['carrier'] ?? null,
                            note: $data['note'] ?? null,
                        );

                        $this->record->refresh();
                    }),

                Actions\Action::make('shipment_picked_up')
                    ->label('Mark Picked Up')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Mark shipment as picked up?')
                    ->modalDescription('This will create a shipment timeline entry. Optionally you can also auto-set order status to SHIPPED.')
                    ->form([
                        Forms\Components\Toggle::make('also_mark_order_shipped')
                            ->label('Also mark Order Status = Shipped')
                            ->default(true),
                        Forms\Components\Textarea::make('note')
                            ->label('Optional note')
                            ->rows(3),
                    ])
                    ->visible(function () {
                        if ($this->record->status === 'cancelled') return false;

                        $shipment = $this->record->shipment;
                        if (! $shipment) return false;
                        if (! $shipment->tracking_number) return false;

                        return in_array($shipment->status, ['pending', 'ready', 'tracking_created'], true);
                    })
                    ->action(function (array $data) {
                        OrderEventService::shipmentPickedUp(
                            order: $this->record,
                            note: $data['note'] ?? null,
                            alsoMarkOrderShipped: (bool) ($data['also_mark_order_shipped'] ?? true),
                        );

                        $this->record->refresh();
                    }),

                Actions\Action::make('shipment_delivered')
                    ->label('Mark Shipment Delivered')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark shipment as delivered?')
                    ->modalDescription('This will create a shipment timeline entry and (optionally) mark Order Status = Delivered.')
                    ->form([
                        Forms\Components\Toggle::make('also_mark_order_delivered')
                            ->label('Also mark Order Status = Delivered')
                            ->default(true),
                        Forms\Components\Textarea::make('note')
                            ->label('Optional note')
                            ->rows(3),
                    ])
                    ->visible(function () {
                        if ($this->record->status === 'cancelled') return false;

                        $shipment = $this->record->shipment;
                        if (! $shipment) return false;

                        return in_array($shipment->status, ['picked_up', 'in_transit', 'out_for_delivery'], true);
                    })
                    ->action(function (array $data) {
                        OrderEventService::shipmentDelivered(
                            order: $this->record,
                            note: $data['note'] ?? null,
                            alsoMarkOrderDelivered: (bool) ($data['also_mark_order_delivered'] ?? true),
                        );

                        $this->record->refresh();
                    }),
            ])
                ->label('Shipment')
                ->icon('heroicon-o-truck'),
        ];
    }

    private function orderStatusHeaderAction(
        string $name,
        string $label,
        string $toStatus,
        array $allowedFrom,
        string $color = 'primary',
    ): Actions\Action {
        return Actions\Action::make($name)
            ->label($label)
            ->color($color)
            ->icon('heroicon-o-arrow-right-circle')
            ->requiresConfirmation()
            ->modalHeading($label . '?')
            ->modalDescription('This will record a timeline entry and notify the customer by email (if possible).')
            ->form([
                Forms\Components\Textarea::make('note')
                    ->label('Optional note')
                    ->rows(3),
            ])
            ->visible(fn () => in_array($this->record->status, $allowedFrom, true))
            ->action(function (array $data) use ($toStatus) {
                OrderEventService::orderStatus($this->record, $toStatus, $data['note'] ?? null);
                $this->record->refresh();
            });
    }

    private function paymentHeaderAction(
        string $name,
        string $label,
        string $toStatus,
        string $color = 'primary',
    ): Actions\Action {
        $disabledBecauseCancelled = fn () => $this->record->status === 'cancelled';

        return Actions\Action::make($name)
            ->label($label)
            ->color($color)
            ->icon('heroicon-o-banknotes')
            ->requiresConfirmation()
            ->modalHeading($label . '?')
            ->modalDescription('This will record a timeline entry and notify the customer by email (if possible).')
            ->form([
                Forms\Components\Textarea::make('note')
                    ->label('Optional note')
                    ->rows(3),
            ])
            ->disabled($disabledBecauseCancelled)
            ->tooltip(fn () => $disabledBecauseCancelled()
                ? 'Payment updates are disabled for cancelled orders.'
                : null
            )
            // ✅ strict flow: unpaid -> paid -> refunded
            ->visible(function () use ($toStatus) {
                $current = $this->record->payment_status;

                return match ($current) {
                    // 'unpaid'   => $toStatus === 'paid',
                    // 'paid'     => $toStatus === 'refunded',
                    'pending' => in_array($toStatus, ['paid', 'failed'], true),
                    'paid' => $toStatus === 'refunded',
                    // 'refunded' => false,
                    default    => false,
                };
            })
            ->action(function (array $data) use ($toStatus) {
                OrderEventService::paymentStatus($this->record, $toStatus, $data['note'] ?? null);
                $this->record->refresh();
            });
    }

    public function infolist(Infolist $infolist): Infolist
    {
        /** @var Order $record */
        $record = $this->getRecord();

        return $infolist
            ->record($record) // ✅ important
            ->schema([
                Tabs::make('OrderTabs')
                    ->contained(false)
                    ->tabs([
                        // =========================================================
                        // OVERVIEW
                        // =========================================================
                        Tab::make('Overview')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Grid::make(3)->schema([
                                    Section::make('Order Summary')
                                        ->icon('heroicon-o-receipt-refund')
                                        ->schema([
                                            Grid::make(2)->schema([
                                                TextEntry::make('order_number')
                                                    ->label('Order #')
                                                    ->copyable()
                                                    ->weight('bold'),

                                                TextEntry::make('created_at')
                                                    ->label('Placed At')
                                                    ->dateTime(),
                                            ]),

                                            Grid::make(2)->schema([
                                                TextEntry::make('status')
                                                    ->label('Order Status')
                                                    ->badge()
                                                    ->color(fn (?string $state) => match ($state) {
                                                        'pending' => 'warning',
                                                        'processing' => 'info',
                                                        'shipped' => 'primary',
                                                        'delivered' => 'success',
                                                        'cancelled' => 'danger',
                                                        default => 'gray',
                                                    }),

                                                TextEntry::make('payment_status')
                                                    ->label('Payment Status')
                                                    ->badge()
                                                    ->color(fn (?string $state) => match ($state) {
                                                        'unpaid' => 'danger',
                                                        'paid' => 'success',
                                                        'refunded' => 'warning',
                                                        'failed' => 'danger',
                                                        default => 'gray',
                                                    }),
                                            ]),

                                            TextEntry::make('total_amount')
                                                ->label('Total')
                                                ->money('LKR')
                                                ->icon('heroicon-o-banknotes')
                                                ->weight('bold'),

                                            TextEntry::make('admin_notes')
                                                ->label('Admin Notes')
                                                ->placeholder('—')
                                                ->columnSpanFull(),
                                        ])
                                        ->columnSpan(1),

                                    Section::make('Customer')
                                        ->icon('heroicon-o-user')
                                        ->schema([
                                            TextEntry::make('customer_name')
                                                ->label('Customer')
                                                ->getStateUsing(function (Order $record) {
                                                    if ($record->user) return $record->user->name;
                                                    $billing = $record->addresses->firstWhere('type', 'billing');
                                                    return $billing?->name ?: 'Guest';
                                                })
                                                ->weight('bold'),

                                            TextEntry::make('customer_email')
                                                ->label('Email')
                                                ->getStateUsing(function (Order $record) {
                                                    if ($record->user?->email) return $record->user->email;
                                                    return $record->addresses->firstWhere('type', 'billing')?->email ?: '—';
                                                })
                                                ->copyable(),

                                            TextEntry::make('customer_phone')
                                                ->label('Phone')
                                                ->getStateUsing(fn (Order $record) =>
                                                    $record->addresses->firstWhere('type', 'billing')?->phone ?: '—'
                                                )
                                                ->copyable(),
                                        ])
                                        ->columnSpan(1),

                                    Section::make('Quick Info')
                                        ->icon('heroicon-o-information-circle')
                                        ->schema([
                                            TextEntry::make('items_count')
                                                ->label('Items')
                                                ->getStateUsing(fn (Order $record) => $record->items->sum('quantity')),

                                            TextEntry::make('products_count')
                                                ->label('Products')
                                                ->getStateUsing(fn (Order $record) => $record->items->count()),

                                            TextEntry::make('customer_type')
                                                ->label('Type')
                                                ->getStateUsing(fn (Order $record) => $record->user ? 'Registered' : 'Guest')
                                                ->badge()
                                                ->color(fn (string $state) => $state === 'Registered' ? 'success' : 'warning'),
                                        ])
                                        ->columnSpan(1),
                                ]),
                            ]),

                        // =========================================================
                        // ADDRESSES
                        // =========================================================
                        Tab::make('Addresses')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Billing Address')
                                        ->icon('heroicon-o-credit-card')
                                        ->schema([
                                            TextEntry::make('billing_block')
                                                ->label('')
                                                ->getStateUsing(fn (Order $record) => $this->formatAddress(
                                                    $record->addresses->firstWhere('type', 'billing')
                                                ))
                                                ->html(),
                                        ]),

                                    Section::make('Shipping Address')
                                        ->icon('heroicon-o-truck')
                                        ->schema([
                                            TextEntry::make('shipping_block')
                                                ->label('')
                                                ->getStateUsing(fn (Order $record) => $this->formatAddress(
                                                    $record->addresses->firstWhere('type', 'shipping')
                                                ))
                                                ->html(),
                                        ]),
                                ]),
                            ]),

                        // =========================================================
                        // ITEMS
                        // =========================================================
                        Tab::make('Items')
                            ->icon('heroicon-o-shopping-bag')
                            ->schema([
                                Section::make('Order Items')
                                    ->schema([
                                        RepeatableEntry::make('items')
                                            ->label('')
                                            ->schema([
                                                TextEntry::make('product_name')
                                                    ->label('Product')
                                                    ->getStateUsing(function (OrderItem $record) {
                                                        return $record->product?->name
                                                            ?? $record->product?->title
                                                            ?? ('Product #' . $record->product_id);
                                                    })
                                                    ->weight('bold'),

                                                TextEntry::make('quantity')
                                                    ->label('Qty')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->getStateUsing(fn (OrderItem $record) => $record->quantity),

                                                TextEntry::make('price')
                                                    ->label('Unit')
                                                    ->money('LKR')
                                                    ->getStateUsing(fn (OrderItem $record) => $record->price),

                                                TextEntry::make('line_total')
                                                    ->label('Line Total')
                                                    ->money('LKR')
                                                    ->weight('bold')
                                                    ->getStateUsing(fn (OrderItem $record) => (float) $record->price * (int) $record->quantity),
                                            ])
                                            ->columns(4),
                                    ]),
                            ]),

                        // =========================================================
                        // TIMELINE (vertical tracking style ✅)
                        // includes: order_status, payment_status, shipment_status
                        // =========================================================
                        Tab::make('Timeline')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Section::make('Timeline')
                                    ->description('Order, payment, and shipment updates (newest first).')
                                    ->schema([
                                        RepeatableEntry::make('statusHistories')
                                            ->label('')
                                            ->state(function (Order $record) {
                                                $items = $record->statusHistories->sortByDesc('created_at')->values();

                                                foreach ($items as $i => $h) {
                                                    $h->setAttribute('_is_last', $i === ($items->count() - 1));
                                                    $h->setAttribute('_is_latest', $i === 0);
                                                }

                                                return $items;
                                            })
                                            ->schema([
                                                TextEntry::make('timeline_row')
                                                    ->label('')
                                                    ->getStateUsing(function (OrderStatusHistory $record) {
                                                        return $this->timelineRowHtml($record);
                                                    })
                                                    ->html()
                                                    ->columnSpanFull(),
                                            ])
                                            ->contained(false)
                                            ->columns(1),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * =============================
     * Helpers (Address + Timeline UI)
     * =============================
     */
    private function formatAddress(?Address $addr): string
    {
        if (! $addr) return '<span class="text-gray-500">—</span>';

        $name = e($addr->name ?? '—');

        $meta = [];
        if ($addr->email) $meta[] = '📧 ' . e($addr->email);
        if ($addr->phone) $meta[] = '📞 ' . e($addr->phone);
        $metaLine = $meta ? '<div class="opacity-80 mt-1">' . implode(' &nbsp; • &nbsp; ', $meta) . '</div>' : '';

        $addrLine = '<div class="mt-2">' . e($addr->address_line) . '</div>';

        $cityLine = trim(implode(', ', array_filter([$addr->city, $addr->state, $addr->postal_code])));
        $cityHtml = $cityLine ? '<div>' . e($cityLine) . '</div>' : '';

        $countryHtml = $addr->country ? '<div>' . e($addr->country) . '</div>' : '';

        return '<div class="p-3 rounded-xl border border-gray-200/60 dark:border-gray-700/60 bg-white dark:bg-gray-900">'
            . '<div class="font-semibold text-sm">' . $name . '</div>'
            . $metaLine
            . $addrLine
            . $cityHtml
            . $countryHtml
            . '</div>';
    }

    /**
     * ✅ Tracking-style vertical timeline row (dot + line + content)
     */
    private function timelineRowHtml(OrderStatusHistory $h): string
    {
        $isLast = (bool) $h->getAttribute('_is_last');
        $isLatest = (bool) $h->getAttribute('_is_latest');

        $typeLabel = match ($h->type) {
            'order_status' => 'Order',
            'payment_status' => 'Payment',
            'shipment_status' => 'Shipment',
            default => 'Event',
        };

        $to = strtoupper((string) $h->to_status);
        $from = $h->from_status ? strtoupper($h->from_status) : null;

        $title = match ($h->type) {
            'order_status' => 'Order ' . $this->statusNice((string) $h->to_status),
            'payment_status' => 'Payment ' . $this->statusNice((string) $h->to_status),
            'shipment_status' => 'Shipment ' . $this->statusNice((string) $h->to_status),
            default => $typeLabel . ' Updated',
        };

        $date = $h->created_at ? $h->created_at->format('M d, Y g:ia') : '';
        $by = $h->changer?->name ?: 'System';

        $dotClass = $isLatest
            ? 'border-green-500 bg-green-500'
            : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900';

        $lineClass = $isLast ? 'hidden' : 'block';

        $noteHtml = '';
        if (! empty($h->note)) {
            $noteHtml = '<div class="mt-2 text-sm text-gray-600 dark:text-gray-300"><span class="font-semibold">Note:</span> ' . e($h->note) . '</div>';
        }

        $fromToHtml = '';
        if ($from) {
            $fromToHtml = '<div class="mt-1 text-sm text-gray-700 dark:text-gray-200">'
                . '<span class="font-semibold">From:</span> ' . e($from)
                . ' <span class="mx-2 text-gray-400">→</span> '
                . '<span class="font-semibold">To:</span> ' . e($to)
                . '</div>';
        }

        return <<<HTML
<div class="flex gap-4">
    <div class="relative flex flex-col items-center">
        <div class="h-5 w-5 rounded-full border-2 {$dotClass}"></div>
        <div class="{$lineClass} w-px flex-1 bg-gray-200 dark:bg-gray-700 mt-2"></div>
    </div>

    <div class="flex-1 pb-8">
        <div class="font-semibold text-base text-gray-900 dark:text-gray-100">{$title}</div>
        <div class="text-sm text-gray-500 dark:text-gray-400">{$date} • By {$by}</div>
        {$fromToHtml}
        {$noteHtml}
    </div>
</div>
HTML;
    }

    private function statusNice(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}