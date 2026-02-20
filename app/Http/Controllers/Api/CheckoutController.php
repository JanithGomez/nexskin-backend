<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderEventService;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    private function getSessionId(Request $request): string
    {
        if (! $request->hasSession()) {
            $request->setLaravelSession(app('session')->driver());
        }

        $request->session()->start();

        return $request->session()->getId();
    }

    private function getCartForCheckout(Request $request): Cart
    {
        $userId = auth('sanctum')->id() ?? $request->user()?->id;
        $sessionId = $this->getSessionId($request);

        if ($userId) {
            return Cart::firstOrCreate(
                ['user_id' => $userId],
                ['session_id' => $sessionId]
            );
        }

        return Cart::firstOrCreate(
            ['session_id' => $sessionId, 'user_id' => null],
            []
        );
    }

    public function placeOrder(Request $request)
    {
        $user = auth('sanctum')->user() ?? $request->user();
        $isGuest = ! $user;

        $rules = [
            'billing' => ['required', 'array'],

            'billing.name' => ['required', 'string', 'max:255'],
            'billing.email' => ['nullable', 'email', 'max:255'],
            'billing.phone' => ['nullable', 'string', 'max:30'],
            'billing.address_line' => ['required', 'string', 'max:255'],
            'billing.city' => ['required', 'string', 'max:255'],
            'billing.state' => ['nullable', 'string', 'max:255'],
            'billing.postal_code' => ['required', 'string', 'max:20'],
            'billing.country' => ['required', 'string', 'max:100'],

            'shipping_same' => ['nullable', 'boolean'],
            'shipping' => ['nullable', 'array'],

            'shipping.name' => ['required_if:shipping_same,false', 'string', 'max:255'],
            'shipping.email' => ['nullable', 'email', 'max:255'],
            'shipping.phone' => ['nullable', 'string', 'max:30'],
            'shipping.address_line' => ['required_if:shipping_same,false', 'string', 'max:255'],
            'shipping.city' => ['required_if:shipping_same,false', 'string', 'max:255'],
            'shipping.state' => ['nullable', 'string', 'max:255'],
            'shipping.postal_code' => ['required_if:shipping_same,false', 'string', 'max:20'],
            'shipping.country' => ['required_if:shipping_same,false', 'string', 'max:100'],

            'notes' => ['nullable', 'string'],
            'payment_method' => ['required', 'in:cod'], // only COD for now
        ];

        // Guest must provide an email somewhere → we enforce billing.email
        if ($isGuest) {
            $rules['billing.email'] = ['required', 'email', 'max:255'];
        }

        $data = $request->validate($rules);

        $cart = $this->getCartForCheckout($request);
        $cart->load(['items.product']);

        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        // Calculate totals on server
        $subtotal = 0;
        foreach ($cart->items as $ci) {
            if (! $ci->product) continue;
            $subtotal += ((float) $ci->price) * ((int) $ci->quantity);
        }

        if ($subtotal <= 0) {
            return response()->json(['message' => 'Invalid cart total'], 422);
        }

        $order = DB::transaction(function () use ($data, $user, $cart, $subtotal) {
            $orderNumber = 'NX-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));

            // ✅ guest_* removed from orders table, so don't save them
            $order = Order::create([
                'user_id' => $user?->id,
                'order_number' => $orderNumber,
                'total_amount' => round($subtotal, 2),
                'status' => 'pending',
                'payment_status' => 'pending',
            ]);
            
            // ✅ Payment row for COD
            $order->payment()->create([
                'payment_method' => 'cod',
                'payment_reference' => null,
                'amount' => $order->total_amount ?? 0,
                'status' => 'pending',
            ]);

            // ✅ optional: timeline + email "Order placed"
            OrderEventService::orderPlaced($order, notifyCustomer: true);

            // order items
            foreach ($cart->items as $ci) {
                if (! $ci->product) continue;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $ci->product_id,
                    'quantity' => (int) $ci->quantity,
                    'price' => (float) $ci->price,
                ]);
            }

            // billing address snapshot (linked to order)
            Address::create([
                'user_id' => $user?->id,
                'order_id' => $order->id,
                'type' => 'billing',
                'name' => $data['billing']['name'],
                'email' => $data['billing']['email'] ?? null,
                'phone' => $data['billing']['phone'] ?? null,
                'address_line' => $data['billing']['address_line'],
                'city' => $data['billing']['city'],
                'state' => $data['billing']['state'] ?? null,
                'postal_code' => $data['billing']['postal_code'],
                'country' => $data['billing']['country'],
            ]);

            $shippingSame = (bool) ($data['shipping_same'] ?? true);

            // shipping address snapshot
            $ship = $shippingSame ? $data['billing'] : ($data['shipping'] ?? []);
            Address::create([
                'user_id' => $user?->id,
                'order_id' => $order->id,
                'type' => 'shipping',
                'name' => $ship['name'] ?? $data['billing']['name'],
                'email' => $ship['email'] ?? ($data['billing']['email'] ?? null),
                'phone' => $ship['phone'] ?? ($data['billing']['phone'] ?? null),
                'address_line' => $ship['address_line'] ?? $data['billing']['address_line'],
                'city' => $ship['city'] ?? $data['billing']['city'],
                'state' => $ship['state'] ?? ($data['billing']['state'] ?? null),
                'postal_code' => $ship['postal_code'] ?? $data['billing']['postal_code'],
                'country' => $ship['country'] ?? $data['billing']['country'],
            ]);

            // clear cart
            $cart->items()->delete();

            return $order;
        });

        return response()->json([
            'message' => 'Order placed',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => (float) $order->total_amount,
            ],
        ], 201);
    }
}