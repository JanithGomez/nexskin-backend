<?php

namespace App\Services;

use App\Mail\OrderUpdateMail;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Shipment;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

class OrderEventService
{
    /**
     * =============================
     * ORDER PLACED (email + timeline)
     * =============================
     */
    public static function orderPlaced(
        Order $order,
        ?string $internalNote = null,
        bool $notifyCustomer = true,
        ?string $noteForEmail = null
    ): void {
        // Ensure payment row exists (COD default)
        $payment = $order->payment()->first();

        if (! $payment) {
            Payment::create([
                'order_id' => $order->id,
                'payment_method' => 'cod',
                'payment_reference' => null,
                'amount' => $order->total_amount ?? 0,
                'status' => $order->payment_status ?: 'pending',
            ]);
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'type' => 'order_status',
            'from_status' => null,
            'to_status' => 'placed',
            'changed_by' => auth()->id(),
            'note' => $internalNote ?: 'Order placed',
        ]);

        if ($notifyCustomer) {
            self::sendMail(
                order: $order,
                type: 'order_status',
                old: null,
                new: 'placed',
                noteForEmail: $noteForEmail
            );
        }
    }

    /**
     * =============================
     * ORDER STATUS (generic)
     * =============================
     */
    public static function orderStatus(
        Order $order,
        string $newStatus,
        ?string $internalNote = null,
        bool $notifyCustomer = true,
        ?string $noteForEmail = null
    ): void {
        $old = $order->status;
        if ($old === $newStatus) return;

        $order->update(['status' => $newStatus]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'type' => 'order_status',
            'from_status' => $old,
            'to_status' => $newStatus,
            'changed_by' => auth()->id(),
            'note' => $internalNote,
        ]);

        if ($notifyCustomer) {
            // ✅ email uses noteForEmail (NOT internalNote)
            self::sendMail($order, 'order_status', $old, $newStatus, $noteForEmail);
        }
    }

    /**
     * =============================
     * PAYMENT STATUS
     * =============================
     * Transitions:
     * - pending -> paid|failed
     * - failed  -> paid (COD retry allowed)
     * - paid    -> refunded
     */
    public static function paymentStatus(
        Order $order,
        string $newStatus,
        ?string $internalNote = null,
        bool $notifyCustomer = true,
        ?string $noteForEmail = null
    ): void {
        $old = $order->payment_status;
        if ($old === $newStatus) return;

        $allowed = match ($old) {
            'pending' => in_array($newStatus, ['paid', 'failed'], true),
            'failed'  => $newStatus === 'paid',
            'paid'    => $newStatus === 'refunded',
            default   => false,
        };

        if (! $allowed) {
            return;
        }

        $order->update(['payment_status' => $newStatus]);

        $payment = $order->payment()->first();
        if (! $payment) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => 'cod',
                'payment_reference' => null,
                'amount' => $order->total_amount ?? 0,
                'status' => $newStatus,
            ]);
        } else {
            $payment->update(['status' => $newStatus]);
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'type' => 'payment_status',
            'from_status' => $old,
            'to_status' => $newStatus,
            'changed_by' => auth()->id(),
            'note' => $internalNote,
        ]);

        if (
            $newStatus === 'paid' &&
            $order->status === 'pending' &&
            $payment?->payment_method === 'bank_transfer'
        ) {
            self::orderStatus(
                order: $order,
                newStatus: 'processing',
                internalNote: 'Auto: Payment approved (bank transfer)',
                notifyCustomer: false,
                noteForEmail: null
            );
        }

        if ($notifyCustomer) {
            self::sendMail($order, 'payment_status', $old, $newStatus, $noteForEmail);
        }
    }

    /**
     * =============================
     * TRACKING CREATED
     * =============================
     */
   

            public static function shipmentTrackingCreated(
            Order $order,
            string $trackingNumber,
            ?string $carrier = null,
            ?string $internalNote = null,
            bool $notifyCustomer = true,
            ?string $noteForEmail = null
        ): void {
            $shipment = $order->shipment()->first();

            if (! $shipment) {
                $shipment = Shipment::create([
                    'order_id' => $order->id,
                    'tracking_number' => $trackingNumber,
                    'carrier' => $carrier,
                    'status' => 'tracking_created',
                    'shipped_at' => null,
                    'delivery_attempts' => 0,
                ]);
                $old = null;
            } else {
                $old = $shipment->status;

                $shipment->update([
                    'tracking_number' => $trackingNumber,
                    'carrier' => $carrier, // ✅ ensure carrier saved
                    'status' => 'tracking_created',
                ]);
            }

            // ✅ timeline keeps internal note
            $meta = self::shipmentMetaNote($carrier, $trackingNumber);
            $finalInternal = trim(implode(' ', array_filter([$meta, $internalNote])));

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'type' => 'shipment_status',
                'from_status' => $old,
                'to_status' => 'tracking_created',
                'changed_by' => auth()->id(),
                'note' => $finalInternal ?: null,
            ]);

            // ✅ IMPORTANT: reload shipment so email always has carrier + tracking
            $order->loadMissing(['shipment', 'addresses', 'user', 'payment']);
            $order->unsetRelation('shipment');
            $order->load('shipment');

            // ✅ Email note ONLY if checkbox enabled
            $finalEmail = $noteForEmail
                ? trim(implode(' ', array_filter([$meta, $noteForEmail])))
                : $meta;

            if ($notifyCustomer) {
                self::sendMail(
                    order: $order,
                    type: 'shipment_status',
                    old: $old,
                    new: 'tracking_created',
                    noteForEmail: $finalEmail ?: null
                );
            }
        }

    /**
     * =============================
     * SHIPMENT PICKED UP (utility)
     * =============================
     */
    public static function shipmentPickedUp(
        Order $order,
        ?string $internalNote = null,
        bool $notifyCustomer = true,
        ?string $noteForEmail = null
    ): void {
        $shipment = $order->shipment()->first();

        if (! $shipment) {
            $shipment = Shipment::create([
                'order_id' => $order->id,
                'tracking_number' => null,
                'carrier' => null,
                'status' => 'picked_up',
                'shipped_at' => now(),
                'delivery_attempts' => 0,
            ]);
            $old = null;
        } else {
            $old = $shipment->status;
            $shipment->update([
                'status' => 'picked_up',
                'shipped_at' => $shipment->shipped_at ?? now(),
            ]);
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'type' => 'shipment_status',
            'from_status' => $old,
            'to_status' => 'picked_up',
            'changed_by' => auth()->id(),
            'note' => $internalNote,
        ]);

        if ($notifyCustomer) {
            self::sendMail($order, 'shipment_status', $old, 'picked_up', $noteForEmail);
        }
    }

    /**
     * =============================
     * ORDER SHIPPED
     * - order.status = shipped (email)
     * - shipment.status = picked_up (timeline only)
     * =============================
     */
    public static function orderShipped(
        Order $order,
        ?string $internalNote = null,
        bool $notifyCustomer = true,
        ?string $noteForEmail = null
    ): void {
        self::orderStatus(
            order: $order,
            newStatus: 'shipped',
            internalNote: $internalNote,
            notifyCustomer: $notifyCustomer,
            noteForEmail: $noteForEmail
        );

        $shipment = $order->shipment()->first();
        if ($shipment) {
            $old = $shipment->status;

            $shipment->update([
                'status' => 'picked_up',
                'shipped_at' => $shipment->shipped_at ?? now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'type' => 'shipment_status',
                'from_status' => $old,
                'to_status' => 'picked_up',
                'changed_by' => auth()->id(),
                'note' => $internalNote ? ('Auto: ' . $internalNote) : 'Auto: Order marked shipped',
            ]);
        }
    }

    /**
     * =============================
     * DELIVERY FAILED
     * - increments shipment.delivery_attempts
     * - COD => payment_status = failed (internal only)
     * - Email uses shipment_status: delivery_failed
     * =============================
     */
    public static function deliveryFailed(
        Order $order,
        ?string $internalNote = null,
        bool $notifyCustomer = true,
        ?string $noteForEmail = null
    ): void {
        $shipment = $order->shipment()->first();

        if (! $shipment) {
            $shipment = Shipment::create([
                'order_id' => $order->id,
                'tracking_number' => null,
                'carrier' => null,
                'status' => 'delivery_failed',
                'shipped_at' => now(),
                'delivery_attempts' => 0,
            ]);
            $old = null;
        } else {
            $old = $shipment->status;
        }

        $shipment->delivery_attempts = (int) $shipment->delivery_attempts + 1;
        $shipment->status = 'delivery_failed';
        $shipment->save();

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'type' => 'shipment_status',
            'from_status' => $old,
            'to_status' => 'delivery_failed',
            'changed_by' => auth()->id(),
            'note' => $internalNote,
        ]);

        $payment = $order->payment()->first();
        if (($payment?->payment_method ?? null) === 'cod') {
            self::paymentStatus(
                order: $order,
                newStatus: 'failed',
                internalNote: 'Auto: Delivery failed (COD not collected)',
                notifyCustomer: false,
                noteForEmail: null
            );
        }

        if ($notifyCustomer) {
            self::sendMail($order, 'shipment_status', $old, 'delivery_failed', $noteForEmail);
        }
    }

    /**
     * =============================
     * DELIVERED
     * - increments attempts
     * - shipment.status = delivered (email)
     * - order.status = delivered (internal only to avoid double email)
     * =============================
     */
    public static function delivered(
        Order $order,
        ?string $internalNote = null,
        bool $notifyCustomer = true,
        ?string $noteForEmail = null
    ): void {
        $shipment = $order->shipment()->first();

        if (! $shipment) {
            $shipment = Shipment::create([
                'order_id' => $order->id,
                'tracking_number' => null,
                'carrier' => null,
                'status' => 'delivered',
                'shipped_at' => now(),
                'delivery_attempts' => 0,
            ]);
            $old = null;
        } else {
            $old = $shipment->status;
        }

        $shipment->delivery_attempts = (int) $shipment->delivery_attempts + 1;
        $shipment->status = 'delivered';
        $shipment->save();

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'type' => 'shipment_status',
            'from_status' => $old,
            'to_status' => 'delivered',
            'changed_by' => auth()->id(),
            'note' => $internalNote,
        ]);

        if ($order->status !== 'delivered' && $order->status !== 'cancelled') {
            self::orderStatus(
                order: $order,
                newStatus: 'delivered',
                internalNote: 'Auto: Shipment delivered',
                notifyCustomer: false,
                noteForEmail: null
            );
        }

        if ($notifyCustomer) {
            self::sendMail($order, 'shipment_status', $old, 'delivered', $noteForEmail);
        }
    }

    /**
     * =============================
     * EMAIL SENDER
     * =============================
     */
    private static function sendMail(
        Order $order,
        string $type,
        ?string $old,
        string $new,
        ?string $noteForEmail = null
    ): void {
        $order->loadMissing(['shipment', 'payment', 'addresses', 'user']);

        $email = $order->user?->email
            ?: $order->addresses->firstWhere('type', 'billing')?->email;

        if (! $email) return;

        try {
            Mail::to($email)->send(new OrderUpdateMail(
                order: $order,
                type: $type,
                oldValue: $old,
                newValue: $new,
                note: $noteForEmail
            ));
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Email failed to send')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private static function shipmentMetaNote(?string $carrier, ?string $trackingNumber): string
    {
        $parts = [];

        if ($carrier) $parts[] = "Carrier: {$carrier}.";
        if ($trackingNumber) $parts[] = "Tracking: {$trackingNumber}.";

        return trim(implode(' ', $parts));
    }
}