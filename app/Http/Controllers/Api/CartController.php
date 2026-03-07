<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CartController extends Controller
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

    private function getOrCreateCart(Request $request): Cart
    {
        $userId = $this->getApiUserFromBearerToken($request)?->id;
        $sessionId = $this->getSessionId($request);

        $sessionCart = Cart::firstOrCreate(
            ['session_id' => $sessionId, 'user_id' => null],
            []
        );

        if ($userId) {
            $userCart = Cart::firstOrCreate(
                ['user_id' => $userId],
                ['session_id' => $sessionId]
            );

            if (! $userCart->session_id) {
                $userCart->session_id = $sessionId;
                $userCart->save();
            }

            if ($sessionCart->id !== $userCart->id && $sessionCart->items()->count()) {
                $sessionCart->load('items');

                foreach ($sessionCart->items as $si) {
                    $existing = CartItem::where('cart_id', $userCart->id)
                        ->where('product_id', $si->product_id)
                        ->first();

                    if ($existing) {
                        $existing->quantity += $si->quantity;
                        $existing->save();
                    } else {
                        CartItem::create([
                            'cart_id' => $userCart->id,
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

    private function cartResponse(Cart $cart)
    {
        $cart->load(['items.product.primaryImage', 'items.product.images']);

        $items = $cart->items
            ->filter(fn ($ci) => $ci->product)
            ->map(function ($ci) {
                $p = $ci->product;

                $primaryPublicId = $p?->primaryImage?->image_url;

                $hoverPublicId = $p?->images
                    ? $p->images->where('id', '!=', optional($p->primaryImage)->id)->first()?->image_url
                    : null;

                return [
                    'item_id' => $ci->id,
                    'id' => $p->id,
                    'title' => $p->name,
                    'price' => (float) $ci->price,
                    'quantity' => (int) $ci->quantity,
                    'imgPublicId' => $primaryPublicId,
                    'hoverPublicId' => $hoverPublicId,
                ];
            })
            ->values()
            ->all();

        $subtotal = collect($items)->reduce(
            fn ($sum, $i) => $sum + ($i['price'] * $i['quantity']),
            0
        );

        return response()->json([
            'items' => $items,
            'subtotal' => round($subtotal, 2),
        ]);
    }

    public function show(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        return $this->cartResponse($cart);
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $qty = (int) ($data['quantity'] ?? 1);
        $cart = $this->getOrCreateCart($request);

        $product = Product::where('is_active', true)->findOrFail($data['product_id']);

        $existing = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->quantity += $qty;
            $existing->save();
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'price' => $product->price,
            ]);
        }

        $cart->refresh();
        return $this->cartResponse($cart);
    }

    public function updateItem(Request $request, int $itemId)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->getOrCreateCart($request);

        $item = CartItem::where('cart_id', $cart->id)->findOrFail($itemId);
        $item->quantity = (int) $data['quantity'];
        $item->save();

        $cart->refresh();
        return $this->cartResponse($cart);
    }

    public function removeItem(Request $request, int $itemId)
    {
        $cart = $this->getOrCreateCart($request);

        $item = CartItem::where('cart_id', $cart->id)->findOrFail($itemId);
        $item->delete();

        $cart->refresh();
        return $this->cartResponse($cart);
    }

    public function clear(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        $cart->items()->delete();

        $cart->refresh();
        return $this->cartResponse($cart);
    }
}