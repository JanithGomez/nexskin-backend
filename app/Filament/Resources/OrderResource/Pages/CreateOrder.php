<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderEventService;
use Filament\Forms;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordCreation(array $data): Order
{
    return DB::transaction(function () use ($data) {

        $items = $data['items'];

        $subtotal = collect($items)
        ->sum(fn($i)=>$i['price']*$i['quantity']);

        $shipping = $data['shipping_fee'] ?? 0;

        $total = $subtotal + $shipping;

        $order = Order::create([

            'user_id'=>$data['user_id'] ?? null,

            'order_number'=>'NX-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),

            'total_amount'=>$total,

            'shipping_fee'=>$shipping,

            'status'=>'processing',

            'payment_status'=>'pending',

            'customer_notes'=>$data['customer_notes'] ?? null,

            'admin_notes'=>$data['admin_notes'] ?? null,

        ]);

        $order->payment()->create([
            'payment_method'=>$data['payment_method'],
            'amount'=>$total,
            'status'=>'pending',
        ]);

        foreach($items as $item){

            OrderItem::create([
                'order_id'=>$order->id,
                'product_id'=>$item['product_id'],
                'quantity'=>$item['quantity'],
                'price'=>$item['price']
            ]);

        }

        Address::create([
            'order_id'=>$order->id,
            'type'=>'billing',
            ...$data['billing']
        ]);

        Address::create([
            'order_id'=>$order->id,
            'type'=>'shipping',
            ...$data['billing']
        ]);

        OrderEventService::orderPlaced($order,true);

        return $order;

    });
}
}