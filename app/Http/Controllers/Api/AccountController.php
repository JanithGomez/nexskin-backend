<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 50)); // safety

        $p = Order::query()
            ->where('user_id', $userId)
            ->with(['shipment', 'payment', 'items'])
            ->latest()
            ->paginate($perPage);

        // transform items but keep paginator meta
        $p->getCollection()->transform(function (Order $o) {
            return [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'total_amount' => (float) $o->total_amount,
                'items_count' => (int) $o->items->sum('quantity'),
                'created_at' => optional($o->created_at)->toIso8601String(),

                // optional (useful later)
                'shipment_status' => $o->shipment?->status,
                'tracking_number' => $o->shipment?->tracking_number,
                'carrier' => $o->shipment?->carrier,
                'delivery_attempts' => (int) ($o->shipment?->delivery_attempts ?? 0),
            ];
        });

        return response()->json([
            'data' => $p->items(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
                'from' => $p->firstItem(),
                'to' => $p->lastItem(),
            ],
            'links' => [
                'first' => $p->url(1),
                'last' => $p->url($p->lastPage()),
                'prev' => $p->previousPageUrl(),
                'next' => $p->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load([
            'items.product',
            'addresses',
            'shipment',
            'payment',
            'statusHistories.changer',
        ]);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total_amount' => (float) $order->total_amount,
                'created_at' => optional($order->created_at)->toIso8601String(),

                'shipment' => $order->shipment ? [
                    'carrier' => $order->shipment->carrier,
                    'tracking_number' => $order->shipment->tracking_number,
                    'status' => $order->shipment->status,
                    'delivery_attempts' => (int) $order->shipment->delivery_attempts,
                    'shipped_at' => optional($order->shipment->shipped_at)->toIso8601String(),
                ] : null,

                'billing' => optional($order->addresses->firstWhere('type', 'billing'))?->only([
                    'name','email','phone','address_line','city','state','postal_code','country'
                ]),
                'shipping' => optional($order->addresses->firstWhere('type', 'shipping'))?->only([
                    'name','email','phone','address_line','city','state','postal_code','country'
                ]),

                'items' => $order->items->map(fn ($i) => [
                    'id' => $i->id,
                    'product_id' => $i->product_id,
                    'title' => $i->product?->name ?? $i->product?->title ?? ('Product #' . $i->product_id),
                    'quantity' => (int) $i->quantity,
                    'price' => (float) $i->price,
                    'line_total' => (float) $i->price * (int) $i->quantity,
                    'imgPublicId' => $i->product?->primaryImage?->image_url
                    ?? $i->product?->images?->firstWhere('is_primary', 1)?->image_url
                    ?? $i->product?->images?->sortBy('sort')?->first()?->image_url
                    ?? null,
                ]),

                'timeline' => $order->statusHistories
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn ($h) => [
                        'type' => $h->type,
                        'from' => $h->from_status,
                        'to' => $h->to_status,
                        'note' => $h->note,
                        'by' => $h->changer?->name ?? 'System',
                        'created_at' => optional($h->created_at)->toIso8601String(),
                    ]),
            ]
        ]);
    }
}