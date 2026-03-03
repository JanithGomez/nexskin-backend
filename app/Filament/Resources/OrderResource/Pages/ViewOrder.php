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
     * ✅ Load heavy relations ONLY for the view page.
     * This keeps the index fast.
     */
    protected function resolveRecord($key): Order
    {
        return Order::query()
            ->with([
                'user',
                'addresses',
                // ✅ product + images are loaded here (not in index)
                'items.product.primaryImage',
                'items.product.images',
                'shipment',
                'payment',
                'statusHistories.changer',
            ])
            ->findOrFail($key);
    }

    /**
     * ✅ SINGLE Header dropdown: fulfillment flow
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('cancel_order')
                    ->label('Cancel Order')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel this order?')
                    ->modalDescription('This will record a timeline entry and notify the customer by email (if possible).')
                    ->form($this->noteForm())
                    ->visible(fn () => in_array($this->record->status, ['pending', 'processing'], true))
                    ->action(function (array $data) {
                        [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

                        OrderEventService::orderStatus(
                            order: $this->record,
                            newStatus: 'cancelled',
                            internalNote: $internalNote,
                            notifyCustomer: true,
                            noteForEmail: $sendToCustomer ? $noteForEmail : null,
                        );

                        $this->record->refresh();
                    }),

                Actions\Action::make('mark_processing')
                    ->label('Mark Processing')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Mark order as processing?')
                    ->modalDescription('Customer will be notified.')
                    ->form($this->noteForm())
                    ->visible(fn () => $this->record->status === 'pending')
                    ->action(function (array $data) {
                        [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

                        OrderEventService::orderStatus(
                            order: $this->record,
                            newStatus: 'processing',
                            internalNote: $internalNote,
                            notifyCustomer: true,
                            noteForEmail: $sendToCustomer ? $noteForEmail : null,
                        );

                        $this->record->refresh();
                    }),

                Actions\Action::make('add_tracking')
                    ->label('Add Tracking')
                    ->icon('heroicon-o-ticket')
                    ->color('primary')
                    ->modalHeading('Add shipment tracking')
                    ->modalDescription('Add tracking number & carrier. Customer will be notified.')
                    ->form([
                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('carrier')
                            ->label('Carrier')
                            ->placeholder('Domex / Pronto / Koombiyo / ...')
                            ->maxLength(255),
                        ...$this->noteForm(),
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
                        if (! in_array($this->record->status, ['processing', 'shipped'], true)) return false;

                        $shipment = $this->record->shipment;
                        return ! $shipment || empty($shipment->tracking_number);
                    })
                    ->action(function (array $data) {
                        [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

                        OrderEventService::shipmentTrackingCreated(
                            order: $this->record,
                            trackingNumber: $data['tracking_number'],
                            carrier: $data['carrier'] ?? null,
                            internalNote: $internalNote,
                            notifyCustomer: true,
                            noteForEmail: $sendToCustomer ? $noteForEmail : null,
                        );

                        $this->record->refresh();
                    }),

                Actions\Action::make('mark_shipped')
                    ->label('Mark Order Shipped')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Mark order as shipped?')
                    ->modalDescription('Customer will be notified.')
                    ->form($this->noteForm())
                    ->visible(function () {
                        if ($this->record->status === 'cancelled') return false;
                        if ($this->record->status !== 'processing') return false;

                        $shipment = $this->record->shipment;
                        return $shipment && ! empty($shipment->tracking_number);
                    })
                    ->action(function (array $data) {
                        [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

                        OrderEventService::orderShipped(
                            order: $this->record,
                            internalNote: $internalNote,
                            notifyCustomer: true,
                            noteForEmail: $sendToCustomer ? $noteForEmail : null,
                        );

                        $this->record->refresh();
                    }),

                Actions\Action::make('mark_paid_cod')
                    ->label('Mark Paid (COD)')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark COD payment as paid?')
                    ->modalDescription('Use this after courier collected cash.')
                    ->form($this->noteForm())
                    ->visible(function () {
                        if ($this->record->status === 'cancelled') return false;

                        $payment = $this->record->payment;
                        if (! $payment) return false;

                        if (($payment->payment_method ?? null) !== 'cod') return false;

                        return in_array($this->record->payment_status, ['pending', 'failed'], true);
                    })
                    ->action(function (array $data) {
                        [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

                        OrderEventService::paymentStatus(
                            order: $this->record,
                            newStatus: 'paid',
                            internalNote: $internalNote,
                            notifyCustomer: true,
                            noteForEmail: $sendToCustomer ? $noteForEmail : null,
                        );

                        $this->record->refresh();
                    }),

                Actions\Action::make('delivery_failed')
                    ->label('Delivery Failed')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Mark delivery failed?')
                    ->modalDescription('This increments delivery attempts and notifies customer. COD will auto-mark payment failed.')
                    ->form($this->noteForm())
                    ->visible(function () {
                        if ($this->record->status === 'cancelled') return false;
                        if ($this->record->status !== 'shipped') return false;

                        $shipment = $this->record->shipment;
                        if (! $shipment) return false;

                        return $shipment->status !== 'delivered';
                    })
                    ->action(function (array $data) {
                        [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

                        OrderEventService::deliveryFailed(
                            order: $this->record,
                            internalNote: $internalNote,
                            notifyCustomer: true,
                            noteForEmail: $sendToCustomer ? $noteForEmail : null,
                        );

                        $this->record->refresh();
                    }),

                Actions\Action::make('mark_delivered')
                    ->label('Mark Delivered')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark order delivered?')
                    ->modalDescription('This increments delivery attempts and notifies customer.')
                    ->form($this->noteForm())
                    ->visible(function () {
                        if ($this->record->status === 'cancelled') return false;
                        if ($this->record->status !== 'shipped') return false;

                        $shipment = $this->record->shipment;
                        if (! $shipment) return false;

                        if ($shipment->status === 'delivered') return false;

                        $payment = $this->record->payment;

                        if ($payment && ($payment->payment_method ?? null) === 'cod') {
                            return $this->record->payment_status === 'paid';
                        }

                        return true;
                    })
                    ->action(function (array $data) {
                        [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

                        OrderEventService::delivered(
                            order: $this->record,
                            internalNote: $internalNote,
                            notifyCustomer: true,
                            noteForEmail: $sendToCustomer ? $noteForEmail : null,
                        );

                        $this->record->refresh();
                    }),
            ])
                ->label('Fulfillment')
                ->icon('heroicon-o-clipboard-document-check'),
        ];
    }

    private function noteForm(): array
    {
        return [
            Forms\Components\Textarea::make('note')
                ->label('Optional note')
                ->rows(3),

            Forms\Components\Toggle::make('send_note_to_customer')
                ->label('Send this note to customer?')
                ->default(false),
        ];
    }

    private function parseNoteData(array $data): array
    {
        $note = $data['note'] ?? null;
        $send = (bool) ($data['send_note_to_customer'] ?? false);

        return [$note, $send, $note];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        /** @var Order $record */
        $record = $this->getRecord();

        return $infolist
            ->record($record)
            ->schema([
                Tabs::make('OrderTabs')
                   ->columnSpanFull()
                    ->tabs([
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
                                                        'pending' => 'warning',
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

    private function timelineRowHtml(OrderStatusHistory $h): string
    {
        $isLast = (bool) $h->getAttribute('_is_last');
        $isLatest = (bool) $h->getAttribute('_is_latest');

        $title = match ($h->type) {
            'order_status' => 'Order ' . $this->statusNice((string) $h->to_status),
            'payment_status' => 'Payment ' . $this->statusNice((string) $h->to_status),
            'shipment_status' => 'Shipment ' . $this->statusNice((string) $h->to_status),
            default => 'Event Updated',
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

        $from = $h->from_status ? strtoupper($h->from_status) : null;
        $to = strtoupper((string) $h->to_status);

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


// namespace App\Filament\Resources\OrderResource\Pages;

// use App\Filament\Resources\OrderResource;
// use App\Models\Address;
// use App\Models\Order;
// use App\Models\OrderItem;
// use App\Models\OrderStatusHistory;
// use App\Services\OrderEventService;
// use Filament\Actions;
// use Filament\Forms;
// use Filament\Infolists\Components\Grid;
// use Filament\Infolists\Components\RepeatableEntry;
// use Filament\Infolists\Components\Section;
// use Filament\Infolists\Components\Tabs;
// use Filament\Infolists\Components\Tabs\Tab;
// use Filament\Infolists\Components\TextEntry;
// use Filament\Infolists\Infolist;
// use Filament\Resources\Pages\ViewRecord;

// class ViewOrder extends ViewRecord
// {
//     protected static string $resource = OrderResource::class;

//     /**
//      * ✅ Load heavy relations ONLY for the view page.
//      * This keeps the index fast.
//      */
//     protected function resolveRecord($key): Order
//     {
//         return Order::query()
//             ->with([
//                 'user',
//                 'addresses',
//                 'items.product.primaryImage',
//                 'items.product.images',
//                 'shipment',
//                 'payment',
//                 'statusHistories.changer',
//             ])
//             ->findOrFail($key);
//     }

//     /**
//      * ✅ SINGLE Header dropdown: fulfillment flow
//      */
//     protected function getHeaderActions(): array
//     {
//         return [
//             Actions\ActionGroup::make([
//                 Actions\Action::make('cancel_order')
//                     ->label('Cancel Order')
//                     ->icon('heroicon-o-x-circle')
//                     ->color('danger')
//                     ->requiresConfirmation()
//                     ->modalHeading('Cancel this order?')
//                     ->modalDescription('This will record a timeline entry and notify the customer by email (if possible).')
//                     ->form($this->noteForm())
//                     ->visible(fn () => in_array($this->record->status, ['pending', 'processing'], true))
//                     ->action(function (array $data) {
//                         [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

//                         OrderEventService::orderStatus(
//                             order: $this->record,
//                             newStatus: 'cancelled',
//                             internalNote: $internalNote,
//                             notifyCustomer: true,
//                             noteForEmail: $sendToCustomer ? $noteForEmail : null,
//                         );

//                         $this->record->refresh();
//                     }),

//                 Actions\Action::make('mark_processing')
//                     ->label('Mark Processing')
//                     ->icon('heroicon-o-arrow-path')
//                     ->color('info')
//                     ->requiresConfirmation()
//                     ->modalHeading('Mark order as processing?')
//                     ->modalDescription('Customer will be notified.')
//                     ->form($this->noteForm())
//                     ->visible(fn () => $this->record->status === 'pending')
//                     ->action(function (array $data) {
//                         [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

//                         OrderEventService::orderStatus(
//                             order: $this->record,
//                             newStatus: 'processing',
//                             internalNote: $internalNote,
//                             notifyCustomer: true,
//                             noteForEmail: $sendToCustomer ? $noteForEmail : null,
//                         );

//                         $this->record->refresh();
//                     }),

//                 Actions\Action::make('add_tracking')
//                     ->label('Add Tracking')
//                     ->icon('heroicon-o-ticket')
//                     ->color('primary')
//                     ->modalHeading('Add shipment tracking')
//                     ->modalDescription('Add tracking number & carrier. Customer will be notified.')
//                     ->form([
//                         Forms\Components\TextInput::make('tracking_number')
//                             ->label('Tracking Number')
//                             ->required()
//                             ->maxLength(255),
//                         Forms\Components\TextInput::make('carrier')
//                             ->label('Carrier')
//                             ->placeholder('Domex / Pronto / Koombiyo / ...')
//                             ->maxLength(255),
//                         ...$this->noteForm(),
//                     ])
//                     ->fillForm(function () {
//                         $shipment = $this->record->shipment;

//                         return [
//                             'tracking_number' => $shipment?->tracking_number,
//                             'carrier' => $shipment?->carrier,
//                         ];
//                     })
//                     ->visible(function () {
//                         if ($this->record->status === 'cancelled') return false;
//                         if (! in_array($this->record->status, ['processing', 'shipped'], true)) return false;

//                         $shipment = $this->record->shipment;

//                         return ! $shipment || empty($shipment->tracking_number);
//                     })
//                     ->action(function (array $data) {
//                         [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

//                         OrderEventService::shipmentTrackingCreated(
//                             order: $this->record,
//                             trackingNumber: $data['tracking_number'],
//                             carrier: $data['carrier'] ?? null,
//                             internalNote: $internalNote,
//                             notifyCustomer: true,
//                             noteForEmail: $sendToCustomer ? $noteForEmail : null,
//                         );

//                         $this->record->refresh();
//                     }),

//                 Actions\Action::make('mark_shipped')
//                     ->label('Mark Order Shipped')
//                     ->icon('heroicon-o-truck')
//                     ->color('primary')
//                     ->requiresConfirmation()
//                     ->modalHeading('Mark order as shipped?')
//                     ->modalDescription('Customer will be notified.')
//                     ->form($this->noteForm())
//                     ->visible(function () {
//                         if ($this->record->status === 'cancelled') return false;
//                         if ($this->record->status !== 'processing') return false;

//                         $shipment = $this->record->shipment;

//                         return $shipment && ! empty($shipment->tracking_number);
//                     })
//                     ->action(function (array $data) {
//                         [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

//                         OrderEventService::orderShipped(
//                             order: $this->record,
//                             internalNote: $internalNote,
//                             notifyCustomer: true,
//                             noteForEmail: $sendToCustomer ? $noteForEmail : null,
//                         );

//                         $this->record->refresh();
//                     }),

//                 Actions\Action::make('mark_paid_cod')
//                     ->label('Mark Paid (COD)')
//                     ->icon('heroicon-o-banknotes')
//                     ->color('success')
//                     ->requiresConfirmation()
//                     ->modalHeading('Mark COD payment as paid?')
//                     ->modalDescription('Use this after courier collected cash.')
//                     ->form($this->noteForm())
//                     ->visible(function () {
//                         if ($this->record->status === 'cancelled') return false;

//                         $payment = $this->record->payment;
//                         if (! $payment) return false;

//                         if (($payment->payment_method ?? null) !== 'cod') return false;

//                         return in_array($this->record->payment_status, ['pending', 'failed'], true);
//                     })
//                     ->action(function (array $data) {
//                         [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

//                         OrderEventService::paymentStatus(
//                             order: $this->record,
//                             newStatus: 'paid',
//                             internalNote: $internalNote,
//                             notifyCustomer: true,
//                             noteForEmail: $sendToCustomer ? $noteForEmail : null,
//                         );

//                         $this->record->refresh();
//                     }),

//                 Actions\Action::make('delivery_failed')
//                     ->label('Delivery Failed')
//                     ->icon('heroicon-o-x-circle')
//                     ->color('danger')
//                     ->requiresConfirmation()
//                     ->modalHeading('Mark delivery failed?')
//                     ->modalDescription('This increments delivery attempts and notifies customer. COD will auto-mark payment failed.')
//                     ->form($this->noteForm())
//                     ->visible(function () {
//                         if ($this->record->status === 'cancelled') return false;
//                         if ($this->record->status !== 'shipped') return false;

//                         $shipment = $this->record->shipment;
//                         if (! $shipment) return false;

//                         return $shipment->status !== 'delivered';
//                     })
//                     ->action(function (array $data) {
//                         [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

//                         OrderEventService::deliveryFailed(
//                             order: $this->record,
//                             internalNote: $internalNote,
//                             notifyCustomer: true,
//                             noteForEmail: $sendToCustomer ? $noteForEmail : null,
//                         );

//                         $this->record->refresh();
//                     }),

//                 Actions\Action::make('mark_delivered')
//                     ->label('Mark Delivered')
//                     ->icon('heroicon-o-check-circle')
//                     ->color('success')
//                     ->requiresConfirmation()
//                     ->modalHeading('Mark order delivered?')
//                     ->modalDescription('This increments delivery attempts and notifies customer.')
//                     ->form($this->noteForm())
//                     ->visible(function () {
//                         if ($this->record->status === 'cancelled') return false;
//                         if ($this->record->status !== 'shipped') return false;

//                         $shipment = $this->record->shipment;
//                         if (! $shipment) return false;

//                         if ($shipment->status === 'delivered') return false;

//                         $payment = $this->record->payment;

//                         if ($payment && ($payment->payment_method ?? null) === 'cod') {
//                             return $this->record->payment_status === 'paid';
//                         }

//                         return true;
//                     })
//                     ->action(function (array $data) {
//                         [$internalNote, $sendToCustomer, $noteForEmail] = $this->parseNoteData($data);

//                         OrderEventService::delivered(
//                             order: $this->record,
//                             internalNote: $internalNote,
//                             notifyCustomer: true,
//                             noteForEmail: $sendToCustomer ? $noteForEmail : null,
//                         );

//                         $this->record->refresh();
//                     }),
//             ])
//                 ->label('Fulfillment')
//                 ->icon('heroicon-o-clipboard-document-check'),
//         ];
//     }

//     private function noteForm(): array
//     {
//         return [
//             Forms\Components\Textarea::make('note')
//                 ->label('Optional note')
//                 ->rows(3),

//             Forms\Components\Toggle::make('send_note_to_customer')
//                 ->label('Send this note to customer?')
//                 ->default(false),
//         ];
//     }

//     private function parseNoteData(array $data): array
//     {
//         $note = $data['note'] ?? null;
//         $send = (bool) ($data['send_note_to_customer'] ?? false);

//         return [$note, $send, $note];
//     }

//     /**
//      * ===== Presentation helpers (NO business logic change) =====
//      */
//     private function colorForOrderStatus(?string $status): string
//     {
//         return match ($status) {
//             'pending' => 'warning',
//             'processing' => 'info',
//             'shipped' => 'primary',
//             'delivered' => 'success',
//             'cancelled' => 'danger',
//             default => 'gray',
//         };
//     }

//     private function colorForPaymentStatus(?string $status): string
//     {
//         return match ($status) {
//             'pending' => 'warning',
//             'paid' => 'success',
//             'failed' => 'danger',
//             'refunded' => 'gray',
//             default => 'gray',
//         };
//     }

//     /**
//      * Use Filament’s built-in badge classes so colors ALWAYS show (no Tailwind purge issues).
//      */
//     private function filamentBadge(string $text, string $color): string
//     {
//         $text = e($text);

//         $colorClass = match ($color) {
//             'success' => 'fi-color-success',
//             'danger' => 'fi-color-danger',
//             'warning' => 'fi-color-warning',
//             'info' => 'fi-color-info',
//             'primary' => 'fi-color-primary',
//             default => 'fi-color-gray',
//         };

//         return "<span class=\"fi-badge {$colorClass} fi-size-sm\">{$text}</span>";
//     }

//     private function moneyLkr($amount): string
//     {
//         return 'LKR ' . number_format((float) $amount, 2);
//     }

//     /**
//      * Full-width “2 pairs per row” table.
//      */
//     private function kvTable(string $rowsHtml): string
//     {
//         return <<<HTML
// <div class="w-full block">
//     <div class="w-full block overflow-hidden rounded-xl border border-gray-200/60 dark:border-gray-700/60">
//         <table class="min-w-full w-full text-sm">
//             <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
//                 {$rowsHtml}
//             </tbody>
//         </table>
//     </div>
// </div>
// HTML;
//     }

//     private function orderSummaryTableHtml(Order $record): string
//     {
//         $orderNumberRaw = (string) $record->order_number;
//         $orderNumberEsc = e($orderNumberRaw);

//         $placedAt = e(optional($record->created_at)?->format('M d, Y g:ia') ?? '—');

//         $orderStatusText = (string) $record->status;
//         $paymentStatusText = (string) $record->payment_status;

//         $orderStatusBadge = $this->filamentBadge($orderStatusText, $this->colorForOrderStatus($orderStatusText));
//         $paymentStatusBadge = $this->filamentBadge($paymentStatusText, $this->colorForPaymentStatus($paymentStatusText));

//         $total = e($this->moneyLkr($record->total_amount));
//         $items = e((string) $record->items->sum('quantity'));
//         $products = e((string) $record->items->count());
//         $notes = e((string) ($record->admin_notes ?? '—'));

//         $copyButton = <<<HTML
// <button
//     type="button"
//     class="ml-2 inline-flex items-center rounded-md border border-gray-500/30 bg-gray-500/10 px-2 py-1 text-xs text-gray-200 hover:bg-gray-500/20"
//     onclick="navigator.clipboard.writeText('{$orderNumberEsc}'); const t=this; const prev=t.innerText; t.innerText='Copied'; setTimeout(()=>t.innerText=prev, 900);"
// >
//     Copy
// </button>
// HTML;

//         $rows = <<<HTML
// <tr class="bg-gray-50/60 dark:bg-gray-900/40">
//     <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Order #</td>
//     <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100 break-all">
//         {$orderNumberEsc}{$copyButton}
//     </td>
//     <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Placed At</td>
//     <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{$placedAt}</td>
// </tr>

// <tr>
//     <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Order Status</td>
//     <td class="px-4 py-3">{$orderStatusBadge}</td>
//     <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Payment Status</td>
//     <td class="px-4 py-3">{$paymentStatusBadge}</td>
// </tr>

// <tr class="bg-gray-50/60 dark:bg-gray-900/40">
//     <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Total</td>
//     <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">{$total}</td>
//     <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Items</td>
//     <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{$items}</td>
// </tr>

// <tr>
//     <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Products</td>
//     <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{$products}</td>
//     <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">—</td>
//     <td class="px-4 py-3 text-gray-900 dark:text-gray-100">—</td>
// </tr>

// <tr class="bg-gray-50/60 dark:bg-gray-900/40">
//     <td class="w-44 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Admin Notes</td>
//     <td class="px-4 py-3 text-gray-900 dark:text-gray-100 whitespace-pre-line break-words" colspan="3">{$notes}</td>
// </tr>
// HTML;

//         return $this->kvTable($rows);
//     }

//     private function customerTableHtml(Order $record): string
//     {
//         $name = $record->user?->name ?: ($record->addresses->firstWhere('type', 'billing')?->name ?: 'Guest');
//         $email = $record->user?->email ?: ($record->addresses->firstWhere('type', 'billing')?->email ?: '—');
//         $phone = $record->addresses->firstWhere('type', 'billing')?->phone ?: '—';

//         $typeText = $record->user ? 'Registered' : 'Guest';
//         $typeColor = $record->user ? 'success' : 'warning';
//         $typeBadge = $this->filamentBadge($typeText, $typeColor);

//         $name = e($name);
//         $email = e($email);
//         $phone = e($phone);

//         $rows = <<<HTML
// <tr class="bg-gray-50/60 dark:bg-gray-900/40">
//     <td class="w-40 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Customer</td>
//     <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100 break-words">{$name}</td>
//     <td class="w-40 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Type</td>
//     <td class="px-4 py-3">{$typeBadge}</td>
// </tr>

// <tr>
//     <td class="w-40 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Email</td>
//     <td class="px-4 py-3 text-gray-900 dark:text-gray-100 break-all">{$email}</td>
//     <td class="w-40 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Phone</td>
//     <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{$phone}</td>
// </tr>
// HTML;

//         return $this->kvTable($rows);
//     }

//     private function addressTableHtml(?Address $addr): string
//     {
//         if (! $addr) {
//             return '<div class="text-sm text-gray-500">—</div>';
//         }

//         $name = e($addr->name ?? '—');
//         $email = e($addr->email ?? '—');
//         $phone = e($addr->phone ?? '—');
//         $line = e($addr->address_line ?? '—');

//         $cityLine = trim(implode(', ', array_filter([$addr->city, $addr->state, $addr->postal_code])));
//         $cityLine = e($cityLine ?: '—');

//         $country = e($addr->country ?? '—');

//         $rows = <<<HTML
// <tr class="bg-gray-50/60 dark:bg-gray-900/40">
//     <td class="w-40 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Name</td>
//     <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100 break-words">{$name}</td>
//     <td class="w-40 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Phone</td>
//     <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{$phone}</td>
// </tr>

// <tr>
//     <td class="w-40 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Email</td>
//     <td class="px-4 py-3 text-gray-900 dark:text-gray-100 break-all">{$email}</td>
//     <td class="w-40 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Country</td>
//     <td class="px-4 py-3 text-gray-900 dark:text-gray-100 break-words">{$country}</td>
// </tr>

// <tr class="bg-gray-50/60 dark:bg-gray-900/40">
//     <td class="w-40 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Address</td>
//     <td class="px-4 py-3 text-gray-900 dark:text-gray-100 break-words" colspan="3">{$line}</td>
// </tr>

// <tr>
//     <td class="w-40 px-4 py-3 font-medium text-gray-600 dark:text-gray-300">City</td>
//     <td class="px-4 py-3 text-gray-900 dark:text-gray-100 break-words" colspan="3">{$cityLine}</td>
// </tr>
// HTML;

//         return $this->kvTable($rows);
//     }

//     private function itemsTableHtml(Order $record): string
//     {
//         $rows = '';

//         foreach ($record->items as $item) {
//             /** @var OrderItem $item */
//             $name = $item->product?->name
//                 ?? $item->product?->title
//                 ?? ('Product #' . $item->product_id);

//             $qty = (int) $item->quantity;
//             $unit = (float) $item->price;
//             $line = $unit * $qty;

//             $name = e($name);
//             $qtyEsc = e((string) $qty);
//             $unitEsc = e($this->moneyLkr($unit));
//             $lineEsc = e($this->moneyLkr($line));

//             $rows .= <<<HTML
// <tr class="border-t border-gray-200/60 dark:border-gray-700/60">
//     <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100 break-words">{$name}</td>
//     <td class="px-4 py-3 text-center text-gray-900 dark:text-gray-100 w-24">{$qtyEsc}</td>
//     <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100 w-44">{$unitEsc}</td>
//     <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100 w-48">{$lineEsc}</td>
// </tr>
// HTML;
//         }

//         if ($rows === '') {
//             $rows = '<tr><td class="px-4 py-4 text-sm text-gray-500" colspan="4">No items.</td></tr>';
//         }

//         return <<<HTML
// <div class="w-full block">
//     <div class="w-full block overflow-hidden rounded-xl border border-gray-200/60 dark:border-gray-700/60">
//         <table class="min-w-full w-full text-sm">
//             <thead class="bg-gray-50/60 dark:bg-gray-900/40">
//                 <tr>
//                     <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Product</th>
//                     <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300 w-24">Qty</th>
//                     <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300 w-44">Unit</th>
//                     <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300 w-48">Line Total</th>
//                 </tr>
//             </thead>
//             <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
//                 {$rows}
//             </tbody>
//         </table>
//     </div>
// </div>
// HTML;
//     }

//     public function infolist(Infolist $infolist): Infolist
//     {
//         /** @var Order $record */
//         $record = $this->getRecord();

//         return $infolist
//             ->record($record)
//             ->schema([
//                 Tabs::make('OrderTabs')
//                     ->columnSpanFull()
//                     ->contained(false)
//                     ->tabs([
//                         Tab::make('Overview')
//                             ->icon('heroicon-o-squares-2x2')
//                             ->schema([
//                                 Grid::make([
//                                     'default' => 1,
//                                     'lg' => 12,
//                                 ])->schema([
//                                     // Full width inside section
//                                     Section::make('Order Summary')
//                                         ->icon('heroicon-o-receipt-refund')
//                                         ->columnSpan([
//                                             'default' => 1,
//                                             'lg' => 8,
//                                         ])
//                                         ->schema([
//                                             TextEntry::make('summary_table')
//                                                 ->label('')
//                                                 ->state(fn (Order $record) => $this->orderSummaryTableHtml($record))
//                                                 ->html()
//                                                 ->columnSpanFull()
//                                                 ->extraAttributes(['class' => 'w-full block']),
//                                         ]),

//                                     Section::make('Customer')
//                                         ->icon('heroicon-o-user')
//                                         ->columnSpan([
//                                             'default' => 1,
//                                             'lg' => 4,
//                                         ])
//                                         ->schema([
//                                             TextEntry::make('customer_table')
//                                                 ->label('')
//                                                 ->state(fn (Order $record) => $this->customerTableHtml($record))
//                                                 ->html()
//                                                 ->columnSpanFull()
//                                                 ->extraAttributes(['class' => 'w-full block']),
//                                         ]),
//                                 ]),
//                             ]),

//                         Tab::make('Addresses')
//                             ->icon('heroicon-o-map-pin')
//                             ->schema([
//                                 Grid::make([
//                                     'default' => 1,
//                                     'lg' => 2,
//                                 ])->schema([
//                                     Section::make('Billing Address')
//                                         ->icon('heroicon-o-credit-card')
//                                         ->schema([
//                                             TextEntry::make('billing_table')
//                                                 ->label('')
//                                                 ->state(fn (Order $record) => $this->addressTableHtml(
//                                                     $record->addresses->firstWhere('type', 'billing')
//                                                 ))
//                                                 ->html()
//                                                 ->columnSpanFull()
//                                                 ->extraAttributes(['class' => 'w-full block']),
//                                         ]),

//                                     Section::make('Shipping Address')
//                                         ->icon('heroicon-o-truck')
//                                         ->schema([
//                                             TextEntry::make('shipping_table')
//                                                 ->label('')
//                                                 ->state(fn (Order $record) => $this->addressTableHtml(
//                                                     $record->addresses->firstWhere('type', 'shipping')
//                                                 ))
//                                                 ->html()
//                                                 ->columnSpanFull()
//                                                 ->extraAttributes(['class' => 'w-full block']),
//                                         ]),
//                                 ]),
//                             ]),

//                         Tab::make('Items')
//                             ->icon('heroicon-o-shopping-bag')
//                             ->schema([
//                                 Section::make('Order Items')
//                                     ->schema([
//                                         TextEntry::make('items_table')
//                                             ->label('')
//                                             ->state(fn (Order $record) => $this->itemsTableHtml($record))
//                                             ->html()
//                                             ->columnSpanFull()
//                                             ->extraAttributes(['class' => 'w-full block']),
//                                     ]),
//                             ]),

//                         Tab::make('Timeline')
//                             ->icon('heroicon-o-clock')
//                             ->schema([
//                                 Section::make('Timeline')
//                                     ->description('Order, payment, and shipment updates (newest first).')
//                                     ->schema([
//                                         RepeatableEntry::make('statusHistories')
//                                             ->label('')
//                                             ->state(function (Order $record) {
//                                                 $items = $record->statusHistories->sortByDesc('created_at')->values();

//                                                 foreach ($items as $i => $h) {
//                                                     $h->setAttribute('_is_last', $i === ($items->count() - 1));
//                                                     $h->setAttribute('_is_latest', $i === 0);
//                                                 }

//                                                 return $items;
//                                             })
//                                             ->schema([
//                                                 TextEntry::make('timeline_row')
//                                                     ->label('')
//                                                     ->getStateUsing(function (OrderStatusHistory $record) {
//                                                         return $this->timelineRowHtml($record);
//                                                     })
//                                                     ->html()
//                                                     ->columnSpanFull(),
//                                             ])
//                                             ->contained(false)
//                                             ->columns(1),
//                                     ]),
//                             ]),
//                     ]),
//             ]);
//     }

//     private function timelineRowHtml(OrderStatusHistory $h): string
//     {
//         $isLast = (bool) $h->getAttribute('_is_last');
//         $isLatest = (bool) $h->getAttribute('_is_latest');

//         $title = match ($h->type) {
//             'order_status' => 'Order ' . $this->statusNice((string) $h->to_status),
//             'payment_status' => 'Payment ' . $this->statusNice((string) $h->to_status),
//             'shipment_status' => 'Shipment ' . $this->statusNice((string) $h->to_status),
//             default => 'Event Updated',
//         };

//         $date = $h->created_at ? $h->created_at->format('M d, Y g:ia') : '';
//         $by = $h->changer?->name ?: 'System';

//         $dotClass = $isLatest
//             ? 'border-green-500 bg-green-500'
//             : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900';

//         $lineClass = $isLast ? 'hidden' : 'block';

//         $noteHtml = '';
//         if (! empty($h->note)) {
//             $noteHtml = '<div class="mt-2 text-sm text-gray-600 dark:text-gray-300"><span class="font-semibold">Note:</span> ' . e($h->note) . '</div>';
//         }

//         $from = $h->from_status ? strtoupper($h->from_status) : null;
//         $to = strtoupper((string) $h->to_status);

//         $fromToHtml = '';
//         if ($from) {
//             $fromToHtml = '<div class="mt-1 text-sm text-gray-700 dark:text-gray-200">'
//                 . '<span class="font-semibold">From:</span> ' . e($from)
//                 . ' <span class="mx-2 text-gray-400">→</span> '
//                 . '<span class="font-semibold">To:</span> ' . e($to)
//                 . '</div>';
//         }

//         return <<<HTML
// <div class="flex gap-4">
//     <div class="relative flex flex-col items-center">
//         <div class="h-5 w-5 rounded-full border-2 {$dotClass}"></div>
//         <div class="{$lineClass} w-px flex-1 bg-gray-200 dark:bg-gray-700 mt-2"></div>
//     </div>

//     <div class="flex-1 pb-8">
//         <div class="font-semibold text-base text-gray-900 dark:text-gray-100">{$title}</div>
//         <div class="text-sm text-gray-500 dark:text-gray-400">{$date} • By {$by}</div>
//         {$fromToHtml}
//         {$noteHtml}
//     </div>
// </div>
// HTML;
//     }

//     private function statusNice(string $status): string
//     {
//         return ucwords(str_replace('_', ' ', $status));
//     }
// }