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
     * ORDER STATUS
     * =============================
     */
    public static function orderStatus(Order $order, string $newStatus, ?string $note = null): void
    {
        $old = $order->status;
        if ($old === $newStatus) {
            return;
        }

        $order->update(['status' => $newStatus]);

        OrderStatusHistory::create([
            'order_id'     => $order->id,
            'type'         => 'order_status',
            'from_status'  => $old,
            'to_status'    => $newStatus,
            'changed_by'   => auth()->id(),
            'note'         => $note,
        ]);

        self::sendMail($order, 'order_status', $old, $newStatus, $note);
    }

    /**
     * =============================
     * PAYMENT STATUS (canonical)
     * payments.status state machine:
     *   pending -> paid -> refunded
     *   pending -> failed
     * =============================
     */
    public static function paymentStatus(Order $order, string $newStatus, ?string $note = null): void
    {
        // We treat payments table as source of truth.
        $payment = $order->payment()->first();

        // If payment row is missing, create it (COD default since your checkout is COD-only now).
        if (! $payment) {
            $payment = Payment::create([
                'order_id'           => $order->id,
                'payment_method'     => 'cod',
                'payment_reference'  => null,
                'amount'             => (float) ($order->total_amount ?? 0),
                'status'             => $order->payment_status ?: 'pending',
            ]);
        }

        $old = $payment->status;
        if ($old === $newStatus) {
            return;
        }

        // ✅ Strict transition rules
        $allowed = match ($old) {
            'pending'  => ['paid', 'failed'],
            'paid'     => ['refunded'],
            'failed'   => [],
            'refunded' => [],
            default    => [],
        };

        if (! in_array($newStatus, $allowed, true)) {
            // ignore invalid transition (or throw)
            return;
        }

        // Update canonical payment first
        $payment->update(['status' => $newStatus]);

        // Keep orders table in sync
        if ($order->payment_status !== $newStatus) {
            $order->update(['payment_status' => $newStatus]);
        }

        // timeline entry
        OrderStatusHistory::create([
            'order_id'     => $order->id,
            'type'         => 'payment_status',
            'from_status'  => $old,
            'to_status'    => $newStatus,
            'changed_by'   => auth()->id(),
            'note'         => $note,
        ]);

        // ✅ Email rules (best practice)
        // - Don't email for "pending" (especially COD checkout)
        // - Email for paid / failed / refunded
        $shouldEmail = in_array($newStatus, ['paid', 'failed', 'refunded'], true);

        if ($shouldEmail) {
            self::sendMail($order, 'payment_status', $old, $newStatus, $note);
        }
    }

    /**
     * =============================
     * SHIPMENT EVENTS
     * =============================
     */

    /**
     * When you create tracking number & carrier.
     */
    public static function shipmentTrackingCreated(
        Order $order,
        ?string $carrier,
        ?string $trackingNumber,
        ?string $note = null
    ): void {
        $shipment = $order->shipment()->first();

        if (! $shipment) {
            $shipment = Shipment::create([
                'order_id'         => $order->id,
                'tracking_number'  => $trackingNumber,
                'carrier'          => $carrier,
                'status'           => 'tracking_created',
                'shipped_at'       => null,
            ]);
        } else {
            $shipment->update([
                'tracking_number' => $trackingNumber,
                'carrier'         => $carrier,
                'status'          => 'tracking_created',
            ]);
        }

        $extra = self::shipmentMetaNote($carrier, $trackingNumber);
        $finalNote = trim(implode(' ', array_filter([$extra, $note])));

        OrderStatusHistory::create([
            'order_id'     => $order->id,
            'type'         => 'shipment_status',
            'from_status'  => null,
            'to_status'    => 'tracking_created',
            'changed_by'   => auth()->id(),
            'note'         => $finalNote ?: null,
        ]);

        self::sendMail($order, 'shipment_status', null, 'tracking_created', $finalNote ?: null);
    }

    /**
     * Courier picked up / collected the parcel.
     */
    public static function shipmentPickedUp(
        Order $order,
        ?string $note = null,
        bool $alsoMarkOrderShipped = true
    ): void {
        $shipment = $order->shipment()->first();

        if (! $shipment) {
            $shipment = Shipment::create([
                'order_id'         => $order->id,
                'tracking_number'  => null,
                'carrier'          => null,
                'status'           => 'picked_up',
                'shipped_at'       => now(),
            ]);
            $old = null;
        } else {
            $old = $shipment->status;
            $shipment->update([
                'status'     => 'picked_up',
                'shipped_at' => $shipment->shipped_at ?? now(),
            ]);
        }

        // optional: also mark order status = shipped
        if ($alsoMarkOrderShipped && $order->status !== 'cancelled') {
            if (in_array($order->status, ['pending', 'processing'], true)) {
                self::orderStatus($order, 'shipped', 'Auto: Shipment picked up');
            }
        }

        $extra = self::shipmentMetaNote($shipment->carrier, $shipment->tracking_number);
        $finalNote = trim(implode(' ', array_filter([$extra, $note])));

        OrderStatusHistory::create([
            'order_id'     => $order->id,
            'type'         => 'shipment_status',
            'from_status'  => $old,
            'to_status'    => 'picked_up',
            'changed_by'   => auth()->id(),
            'note'         => $finalNote ?: null,
        ]);

        self::sendMail($order, 'shipment_status', $old, 'picked_up', $finalNote ?: null);
    }

    public static function shipmentDelivered(
        Order $order,
        ?string $note = null,
        bool $alsoMarkOrderDelivered = true
    ): void {
        $shipment = $order->shipment()->first();

        if (! $shipment) {
            $shipment = Shipment::create([
                'order_id'         => $order->id,
                'tracking_number'  => null,
                'carrier'          => null,
                'status'           => 'delivered',
                'shipped_at'       => now(),
            ]);
            $old = null;
        } else {
            $old = $shipment->status;
            $shipment->update([
                'status' => 'delivered',
            ]);
        }

        // optional: also mark order status = delivered
        if ($alsoMarkOrderDelivered && $order->status !== 'cancelled') {
            if ($order->status !== 'delivered') {
                self::orderStatus($order, 'delivered', 'Auto: Shipment delivered');
            }
        }

        $extra = self::shipmentMetaNote($shipment->carrier, $shipment->tracking_number);
        $finalNote = trim(implode(' ', array_filter([$extra, $note])));

        OrderStatusHistory::create([
            'order_id'     => $order->id,
            'type'         => 'shipment_status',
            'from_status'  => $old,
            'to_status'    => 'delivered',
            'changed_by'   => auth()->id(),
            'note'         => $finalNote ?: null,
        ]);

        self::sendMail($order, 'shipment_status', $old, 'delivered', $finalNote ?: null);
    }

    /**
     * =============================
     * EMAIL SENDER (single place)
     * =============================
     */
    private static function sendMail(
        Order $order,
        string $type,
        ?string $old,
        string $new,
        ?string $note = null
    ): void {
        $order->loadMissing([
            'shipment',
            'payment',
            'addresses',
            'user',
        ]);

        $email = $order->user?->email;

        if (! $email) {
            $billing = $order->addresses->firstWhere('type', 'billing');
            $email = $billing?->email;
        }

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new OrderUpdateMail(
                order: $order,
                type: $type,
                oldValue: $old,
                newValue: $new,
                note: $note, // make sure your mailable supports this
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

    /**
     * Builds a consistent note with carrier + tracking
     */
    private static function shipmentMetaNote(?string $carrier, ?string $trackingNumber): string
    {
        $parts = [];

        if ($carrier) {
            $parts[] = "Carrier: {$carrier}.";
        }
        if ($trackingNumber) {
            $parts[] = "Tracking: {$trackingNumber}.";
        }

        return trim(implode(' ', $parts));
    }
}