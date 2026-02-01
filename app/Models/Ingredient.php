<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'inci_name',
        'is_allergen',
        'description',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_ingredient');
    }
}
