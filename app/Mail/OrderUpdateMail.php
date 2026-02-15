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

        return $this
            ->subject("{$label} updated for {$this->order->order_number}")
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