<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'tracking_number',
        'carrier',
        'status',
        'shipped_at',
        'delivery_attempts',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivery_attempts' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}