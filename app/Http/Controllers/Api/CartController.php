<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    private function getSessionId(Request $request): string
    {
        // Ensure session exists
        if (! $request->hasSession()) {
            $request->setLaravelSession(app('session')->driver());
        }
        $request->session()->start();

        return $request->session()->getId();
    }

    private function getOrCreateCart(Request $request): Cart
    {
        $userId = Auth::id();
        $sessionId = $this->getSessionId($request);

        // If logged in, prefer user cart; else session cart
        if ($userId) {
            $cart = Cart::firstOrCreate(
                ['user_id' => $userId],
                ['session_id' => $sessionId]
            );

            // keep session_id updated
            if (! $cart->session_id) {
                $cart->session_id = $sessionId;
                $cart->save();
            }

            return $cart;
        }

        return Cart::firstOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => null]
        );
    }

    private function cartResponse(Cart $cart)
    {
        $cart->load(['items.product.primaryImage', 'items.product.images']);

        $items = $cart->items->map(function ($ci) {
            $p = $ci->product;

            // match your Next UI shape:
            // id = product id (so links work), plus item_id for updating/deleting
            return [
                'item_id' => $ci->id,                 // cart_items.id
                'id' => $p->id,                       // product_id
                'title' => $p->name,
                'price' => (float) $ci->price,        // stored price
                'quantity' => (int) $ci->quantity,

                // these must exist in your product api already
                'imgSrc' => $p->primaryImage
                    ? \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::getUrl($p->primaryImage->image_url, [
                        'secure' => true,
                        'quality' => 'auto',
                        'fetch_format' => 'auto',
                        'width' => 720,
                        'crop' => 'scale',
                    ])
                    : asset('images/placeholder.png'),
            ];
        })->values();

        $subtotal = $items->reduce(fn ($sum, $i) => $sum + ($i['price'] * $i['quantity']), 0);

        return response()->json([
            'items' => $items,
            'subtotal' => round($subtotal, 2),
        ]);
    }

    // ✅ GET /api/cart
    public function show(Request $request)
    {
        $cart = $this->getOrCreateCart($request);

        return $this->cartResponse($cart);
    }

    // ✅ POST /api/cart/items
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
                'price' => $product->price, // snapshot price
            ]);
        }

        return $this->cartResponse($cart);
    }

    // ✅ PATCH /api/cart/items/{itemId}
    public function updateItem(Request $request, int $itemId)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->getOrCreateCart($request);

        $item = CartItem::where('cart_id', $cart->id)->findOrFail($itemId);
        $item->quantity = (int) $data['quantity'];
        $item->save();

        return $this->cartResponse($cart);
    }

    // ✅ DELETE /api/cart/items/{itemId}
    public function removeItem(Request $request, int $itemId)
    {
        $cart = $this->getOrCreateCart($request);

        $item = CartItem::where('cart_id', $cart->id)->findOrFail($itemId);
        $item->delete();

        return $this->cartResponse($cart);
    }
}
