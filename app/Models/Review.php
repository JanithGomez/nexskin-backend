<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $casts = [
        'media' => 'array',
        'is_approved' => 'boolean',
        'is_anonymous' => 'boolean',
    ];

    protected $fillable = [
        'user_id',
        'product_id',
        'rating',
        'review_title',
        'comment',
        'media',
        'is_approved',
        'is_anonymous',
        'guest_name',
        'guest_email',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }
}
