<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderEventService;
use App\Support\EmailHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

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

    private function getApiUserFromBearerToken(Request $request): ?User
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken) {
            return null;
        }

        $tokenable = $accessToken->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }

    private function getCartForCheckout(Request $request): Cart
    {
        $user = $this->getApiUserFromBearerToken($request);
        $sessionId = $this->getSessionId($request);

        $sessionCart = Cart::firstOrCreate(
            ['session_id' => $sessionId, 'user_id' => null],
            []
        );

        if ($user) {
            $userCart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['session_id' => $sessionId]
            );

            if (! $userCart->session_id) {
                $userCart->session_id = $sessionId;
                $userCart->save();
            }

            if ($sessionCart->id !== $userCart->id && $sessionCart->items()->count()) {
                $sessionCart->load('items');

                foreach ($sessionCart->items as $si) {
                    $existing = $userCart->items()
                        ->where('product_id', $si->product_id)
                        ->first();

                    if ($existing) {
                        $existing->quantity += $si->quantity;
                        $existing->save();
                    } else {
                        $userCart->items()->create([
                            'product_id' => $si->product_id,
                            'quantity' => $si->quantity,
                            'price' => $si->price,
                        ]);
                    }
                }

                $sessionCart->items()->delete();
                $sessionCart->delete();
            }

            return $userCart;
        }

        return $sessionCart;
    }

    public function placeOrder(Request $request)
    {
        $user = $this->getApiUserFromBearerToken($request);
        $isGuest = ! $user;

        $rules = [
            'billing' => ['required', 'array'],

            'billing.name' => ['required', 'string', 'max:255'],
            'billing.email' => ['nullable', 'email:rfc,dns', 'indisposable', 'max:255'],
            'billing.phone' => ['nullable', 'string', 'max:30'],
            'billing.address_line' => ['required', 'string', 'max:255'],
            'billing.city' => ['required', 'string', 'max:255'],
            'billing.state' => ['nullable', 'string', 'max:255'],
            'billing.postal_code' => ['required', 'string', 'max:20'],
            'billing.country' => ['required', 'string', 'max:100'],

            'shipping_same' => ['nullable', 'boolean'],
            'shipping' => ['nullable', 'array'],

            'shipping.name' => ['required_if:shipping_same,false', 'string', 'max:255'],
            'shipping.email' => ['nullable', 'email:rfc,dns', 'indisposable', 'max:255'],
            'shipping.phone' => ['nullable', 'string', 'max:30'],
            'shipping.address_line' => ['required_if:shipping_same,false', 'string', 'max:255'],
            'shipping.city' => ['required_if:shipping_same,false', 'string', 'max:255'],
            'shipping.state' => ['nullable', 'string', 'max:255'],
            'shipping.postal_code' => ['required_if:shipping_same,false', 'string', 'max:20'],
            'shipping.country' => ['required_if:shipping_same,false', 'string', 'max:100'],

            'notes' => ['nullable', 'string'],
            'payment_method' => ['required', 'in:cod'],
        ];

        if ($isGuest) {
            $rules['billing.email'] = ['required', 'email:rfc,dns', 'indisposable', 'max:255'];
        }

        $data = $request->validate($rules);

        $billingEmailSuggestion = EmailHelper::suggest($data['billing']['email'] ?? null);

        if ($billingEmailSuggestion) {
            return response()->json([
                'message' => 'Please confirm your billing email address.',
                'field' => 'billing.email',
                'email_suggestion' => $billingEmailSuggestion,
            ], 422);
        }

        $shippingSame = (bool) ($data['shipping_same'] ?? true);

        if ($shippingSame === false) {
            $shippingEmailSuggestion = EmailHelper::suggest($data['shipping']['email'] ?? null);

            if ($shippingEmailSuggestion) {
                return response()->json([
                    'message' => 'Please confirm your shipping email address.',
                    'field' => 'shipping.email',
                    'email_suggestion' => $shippingEmailSuggestion,
                ], 422);
            }
        }

        $cart = $this->getCartForCheckout($request);
        $cart->load(['items.product']);

        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        $subtotal = 0;

        foreach ($cart->items as $ci) {
            if (! $ci->product) {
                continue;
            }

            $subtotal += ((float) $ci->price) * ((int) $ci->quantity);
        }

        if ($subtotal <= 0) {
            return response()->json(['message' => 'Invalid cart total'], 422);
        }

        $order = DB::transaction(function () use ($data, $user, $cart, $subtotal, $shippingSame) {
            $orderNumber = 'NX-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));

            $order = Order::create([
                'user_id' => $user?->id,
                'order_number' => $orderNumber,
                'total_amount' => round($subtotal, 2),
                'status' => 'pending',
                'payment_status' => 'pending',
            ]);

            $order->payment()->create([
                'payment_method' => 'cod',
                'payment_reference' => null,
                'amount' => $order->total_amount ?? 0,
                'status' => 'pending',
            ]);

            foreach ($cart->items as $ci) {
                if (! $ci->product) {
                    continue;
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $ci->product_id,
                    'quantity' => (int) $ci->quantity,
                    'price' => (float) $ci->price,
                ]);
            }

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

            $cart->items()->delete();

            return $order;
        });

        OrderEventService::orderPlaced($order, notifyCustomer: true);

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