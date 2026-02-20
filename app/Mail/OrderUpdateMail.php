<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;

class OrderUpdateMail extends Mailable
{
    public function __construct(
        public Order $order,
        public string $type,          // order_status | payment_status | shipment_status
        public ?string $oldValue,
        public string $newValue,
        public ?string $note = null,  // ✅ new
    ) {}

    public function build()
    {
        $label = match ($this->type) {
            'payment_status'  => 'Payment status',
            'shipment_status' => 'Shipment status',
            default           => 'Order status',
        };

        $new = strtolower($this->newValue);

        $subject = match (true) {
            $this->type === 'order_status' && $new === 'placed'
                => "We received your order {$this->order->order_number}",
            $this->type === 'order_status' && $new === 'processing'
                => "Your order is being prepared ({$this->order->order_number})",
            $this->type === 'order_status' && $new === 'shipped'
                => "Your order has been shipped ({$this->order->order_number})",
            $this->type === 'order_status' && $new === 'delivered'
                => "Delivered: {$this->order->order_number}",
            $this->type === 'shipment_status' && $new === 'delivery_failed'
                => "Delivery attempt failed ({$this->order->order_number})",
            default
                => "{$label} updated for {$this->order->order_number}",
        };

        return $this
            ->subject($subject)
            ->view('emails.order-update')
            ->with([
                'order' => $this->order,
                'type' => $this->type,
                'oldValue' => $this->oldValue,
                'newValue' => $this->newValue,
                'note' => $this->note,
                'label' => $label,
            ]);
    }

}