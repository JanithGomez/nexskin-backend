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
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

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

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([

            Forms\Components\Grid::make(3)->schema([

                /*
                LEFT SIDE
                */

                Forms\Components\Group::make()->schema([

                    Forms\Components\Section::make('Customer')
                        ->schema([

                            // Forms\Components\Select::make('user_id')
                            //     ->relationship('user','email')
                            //     ->searchable()
                            //     ->preload()
                            //     ->reactive(),

                            Forms\Components\Select::make('user_id')
                            ->relationship('user','email')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {

                                if(!$state) return;

                                $address = \App\Models\Address::where('user_id',$state)
                                    ->where('type','billing')
                                    ->first();

                                if(!$address) return;

                                $set('billing.name',$address->name);
                                $set('billing.email',$address->email);
                                $set('billing.phone',$address->phone);

                                $set('billing.address_line',$address->address_line);
                                $set('billing.city',$address->city);
                                $set('billing.postal_code',$address->postal_code);
                                $set('billing.country',$address->country);

                                $set('billing.state_id',$address->state_id);

                            }),

                            Forms\Components\TextInput::make('billing.name')
                                ->required(),

                            Forms\Components\TextInput::make('billing.email')
                                ->email(),

                            Forms\Components\TextInput::make('billing.phone'),
                        ]),

                    Forms\Components\Section::make('Address')
                        ->schema([

                            Forms\Components\TextInput::make('billing.address_line')
                                ->required(),

                           Forms\Components\Select::make('billing.state_id')
                                ->label('State')
                                ->options(\App\Models\State::pluck('name','id'))
                                ->searchable()
                                ->preload()
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {

                                    $shipping = \App\Models\ShippingFee::where('state_id', $state)->first();

                                    if ($shipping) {

                                        $set('shipping_fee', $shipping->price);

                                        $subtotal = $get('subtotal') ?? 0;

                                        $set('total_amount', $subtotal + $shipping->price);

                                    }

                                })
                                ->required(),       

                            Forms\Components\TextInput::make('billing.city')->required(),

                            Forms\Components\TextInput::make('billing.postal_code')->required(),

                            Forms\Components\TextInput::make('billing.country')
                                ->default('Sri Lanka')
                                ->required(),
                        ]),

                ])->columnSpan(2),

                /*
                RIGHT SIDE
                */

                Forms\Components\Group::make()->schema([


                    Forms\Components\Section::make('Order Summary')
                            ->schema([

                                Forms\Components\Placeholder::make('order_number_preview')
                                    ->label('Order Number')
                                    ->content(fn () => 'NX-' . now()->format('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(4))),

                                Forms\Components\Hidden::make('subtotal'),
                                Forms\Components\Hidden::make('shipping_fee'),
                                Forms\Components\Hidden::make('total_amount'),

                                Forms\Components\Placeholder::make('subtotal_view')
                                    ->label('Subtotal')
                                    ->content(fn($get)=>'LKR '.number_format($get('subtotal')??0,2)),

                                Forms\Components\Placeholder::make('shipping_view')
                                    ->label('Shipping')
                                    ->content(fn($get)=>'LKR '.number_format($get('shipping_fee')??0,2)),

                                Forms\Components\Placeholder::make('total_view')
                                    ->label('Total')
                                    ->content(fn($get)=>'LKR '.number_format($get('total_amount')??0,2)),

                    ]),

                    Forms\Components\Section::make('Payment')
                        ->schema([

                            Forms\Components\Select::make('payment_method')
                                ->options([
                                    'cod'=>'Cash on Delivery',
                                    'bank_transfer' => 'Bank Transfer',
                                ])
                                ->reactive()
                                ->required(),
                        ]),

                            Forms\Components\FileUpload::make('payment_reference')
                                ->label('Bank Slip')
                                ->image()
                                ->disk('cloudinary') // or 'public' if local
                                ->directory('payments')
                                ->visibility('public')
                                ->imagePreviewHeight('150')
                                ->openable()
                                ->downloadable()
                                ->visible(fn ($get) => $get('payment_method') === 'bank_transfer')
                                ->required(fn ($get) => $get('payment_method') === 'bank_transfer')
                                ->maxSize(2048) // 2MB
                                ->helperText('Upload bank payment slip (screenshot or photo)'),

                    Forms\Components\Section::make('Notes')
                        ->schema([

                            Forms\Components\Textarea::make('customer_notes'),

                            Forms\Components\Textarea::make('admin_notes'),

                        ])

                ])->columnSpan(1)

            ]),

            /*
            FULL WIDTH PRODUCTS SECTION
            */

            Forms\Components\Section::make('Products')
                ->schema([

                    Forms\Components\Repeater::make('items')
                        ->schema([

                            Forms\Components\Select::make('product_id')
                                ->label('Product')
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search) =>
                                    \App\Models\Product::where('name','like',"%{$search}%")
                                        ->limit(50)
                                        ->pluck('name','id')
                                )
                                ->getOptionLabelUsing(fn ($value) =>
                                    \App\Models\Product::find($value)?->name
                                )
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {

                                    $product = \App\Models\Product::with('primaryImage')->find($state);

                                    if (!$product) return;

                                    $set('price',$product->price);
                                    $set('stock',$product->stock);

                                    $set(
                                        'image',
                                        $product->primaryImage
                                            ? Cloudinary::getUrl($product->primaryImage->image_url)
                                            : null
                                    );
                                })
                                ->required()
                                ->columnSpan(2),

                            Forms\Components\Hidden::make('image'),

                            Forms\Components\Placeholder::make('image_preview')
                                ->label('Image')
                                ->content(function ($get) {

                                    $image = $get('image');

                                    if (!$image) return '—';

                                    return new \Illuminate\Support\HtmlString(
                                        "<img src='{$image}' style='height:60px;width:60px;object-fit:cover;border-radius:8px'>"
                                    );
                                })
                                ->columnSpan(1),

                            Forms\Components\Hidden::make('stock'),

                            Forms\Components\Placeholder::make('stock_view')
                                ->label('Stock')
                                ->content(function ($get){

                                    $stock = $get('stock');

                                    if ($stock === null) return '—';

                                    $color = $stock > 10 ? 'green' : 'red';

                                    return new \Illuminate\Support\HtmlString(
                                        "<span style='padding:4px 10px;background:{$color};color:white;border-radius:6px;font-size:12px'>
                                            {$stock} in stock
                                        </span>"
                                    );
                                })
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('quantity')
                                ->numeric()
                                ->default(1)
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $get, callable $set){

                                    $stock = $get('stock');

                                    if ($stock && $state > $stock) {

                                        $set('quantity',$stock);

                                        \Filament\Notifications\Notification::make()
                                            ->title('Stock limit reached')
                                            ->body("Only {$stock} items available")
                                            ->danger()
                                            ->send();
                                    }
                                })
                                ->required()
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('price')
                                ->numeric()
                                ->required()
                                ->prefix('LKR')
                                ->columnSpan(2),

                        ])
                        ->columns(7)
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {

                            $items = $get('items');

                            $subtotal = collect($items)
                                ->sum(fn($i)=>($i['price'] ?? 0) * ($i['quantity'] ?? 1));

                            $set('subtotal',$subtotal);

                            $shipping = $get('shipping_fee') ?? 0;

                            $set('total_amount',$subtotal + $shipping);

                        })
                        ->defaultItems(1)
                        ->required(),

                ])
                ->columnSpanFull(),
        ]);
        // ]);
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
        return true;
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
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
    
}