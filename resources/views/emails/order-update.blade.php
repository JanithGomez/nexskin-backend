<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Order Update</title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;font-family:Arial, Helvetica, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb;padding:24px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,0.08);">
                <tr>
                    <td style="background:#111827;color:#fff;padding:20px 24px;">
                        <div style="font-size:18px;font-weight:700;">NexSkin</div>
                        <div style="opacity:.85;margin-top:4px;">Order update</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px;">
                        @php
                            // ------------------------------
                            // Label by type
                            // ------------------------------
                            $label = match ($type ?? 'order_status') {
                                'payment_status'  => 'Payment status',
                                'shipment_status' => 'Shipment status',
                                default           => 'Order status',
                            };

                            // ------------------------------
                            // Badge colors by type + status
                            // (fallback to neutral)
                            // ------------------------------
                            $statusKey = strtolower((string) ($newValue ?? ''));

                            $palette = [
                                // order statuses
                                'pending'    => ['bg' => '#FEF3C7', 'text' => '#92400E'], // amber
                                'processing' => ['bg' => '#DBEAFE', 'text' => '#1E40AF'], // blue
                                'shipped'    => ['bg' => '#E0E7FF', 'text' => '#3730A3'], // indigo
                                'delivered'  => ['bg' => '#D1FAE5', 'text' => '#065F46'], // green
                                'cancelled'  => ['bg' => '#FEE2E2', 'text' => '#991B1B'], // red

                                // payment statuses
                                'unpaid'    => ['bg' => '#FEE2E2', 'text' => '#991B1B'],
                                'paid'      => ['bg' => '#D1FAE5', 'text' => '#065F46'],
                                'refunded'  => ['bg' => '#FEF3C7', 'text' => '#92400E'],
                                'failed'    => ['bg' => '#FEE2E2', 'text' => '#991B1B'],

                                // shipment statuses (your custom ones)
                                'tracking_created' => ['bg' => '#DBEAFE', 'text' => '#1E40AF'],
                                'picked_up'        => ['bg' => '#FEF3C7', 'text' => '#92400E'],
                                'in_transit'       => ['bg' => '#E0E7FF', 'text' => '#3730A3'],
                                'out_for_delivery' => ['bg' => '#FEF3C7', 'text' => '#92400E'],
                                'delivered'        => ['bg' => '#D1FAE5', 'text' => '#065F46'],
                            ];

                            $badgeBg = $palette[$statusKey]['bg'] ?? '#F3F4F6';
                            $badgeText = $palette[$statusKey]['text'] ?? '#374151';

                            // ------------------------------
                            // Try to extract tracking details
                            // (we put "Carrier: X. Tracking: Y." into the note in service)
                            // ------------------------------
                            $carrier = null;
                            $tracking = null;

                            if (($type ?? null) === 'shipment_status') {
                                // best source: shipment relation if loaded
                                $carrier = $order->shipment->carrier ?? null;
                                $tracking = $order->shipment->tracking_number ?? null;

                                // fallback: parse note (if relation isn't loaded)
                                if ((!$carrier || !$tracking) && !empty($note)) {
                                    if (preg_match('/Carrier:\s*([^\.]+)\./i', $note, $m)) $carrier = $carrier ?: trim($m[1]);
                                    if (preg_match('/Tracking:\s*([^\.]+)\./i', $note, $m)) $tracking = $tracking ?: trim($m[1]);
                                }
                            }

                            $prettyOld = $oldValue ? strtoupper($oldValue) : null;
                            $prettyNew = strtoupper((string) $newValue);

                            // Nice title line
                            $headline = $label . ' updated';
                            if (($type ?? null) === 'shipment_status') {
                                $headline = 'Shipment update';
                            }
                        @endphp

                        <h2 style="margin:0 0 10px;font-size:20px;color:#111827;">
                            {{ $headline }}
                        </h2>

                        <p style="margin:0 0 16px;color:#374151;line-height:1.5;">
                            Your order <strong>{{ $order->order_number }}</strong> has been updated.
                        </p>

                        {{-- Shipment tracking info (only when available) --}}
                        @if(($type ?? null) === 'shipment_status' && ($carrier || $tracking))
                            <div style="padding:12px 14px;border:1px dashed #c7d2fe;border-radius:10px;background:#eef2ff;margin-bottom:14px;">
                                <div style="font-size:13px;color:#111827;font-weight:700;margin-bottom:6px;">Tracking details</div>
                                <div style="font-size:13px;color:#374151;line-height:1.5;">
                                    @if($carrier)
                                        <div><span style="color:#6b7280;">Carrier:</span> <strong>{{ $carrier }}</strong></div>
                                    @endif
                                    @if($tracking)
                                        <div><span style="color:#6b7280;">Tracking #:</span> <strong>{{ $tracking }}</strong></div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div style="padding:14px 16px;border:1px solid #e5e7eb;border-radius:10px;">
                            <div style="display:flex;gap:12px;align-items:center;">
                                <div style="font-size:13px;color:#6b7280;width:120px;">{{ $label }}</div>
                                <div>
                                    @if($prettyOld)
                                        <span style="display:inline-block;padding:6px 10px;border-radius:999px;background:#f3f4f6;color:#374151;font-size:12px;">
                                            {{ $prettyOld }}
                                        </span>
                                        <span style="margin:0 8px;color:#9ca3af;">→</span>
                                    @endif

                                    <span style="display:inline-block;padding:6px 10px;border-radius:999px;background:{{ $badgeBg }};color:{{ $badgeText }};font-size:12px;font-weight:700;">
                                        {{ $prettyNew }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Optional note --}}
                        @if(!empty($note))
                            <div style="margin-top:14px;padding:12px 14px;border:1px solid #e5e7eb;border-radius:10px;background:#fafafa;">
                                <div style="font-size:13px;color:#111827;font-weight:700;margin-bottom:6px;">Note</div>
                                <div style="font-size:13px;color:#374151;line-height:1.6;">
                                    {{ $note }}
                                </div>
                            </div>
                        @endif

                        <div style="margin-top:18px;color:#6b7280;font-size:12px;line-height:1.5;">
                            If you did not expect this update, reply to this email and we will help.
                        </div>

                        <hr style="border:none;border-top:1px solid #e5e7eb;margin:22px 0;">

                        <div style="color:#6b7280;font-size:12px;">
                            © {{ date('Y') }} NexSkin. All rights reserved.
                        </div>
                    </td>
                </tr>
            </table>

            <div style="margin-top:14px;color:#9ca3af;font-size:12px;">
                This is an automated message, please do not share sensitive info by email.
            </div>
        </td>
    </tr>
</table>
</body>
</html>
