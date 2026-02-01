<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
// use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'benefits',
        'how_to_use',
        'price',
        'stock',
        'brand_id',
        'category_id',
        'skin_type_id',
        'is_active',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function skinType()
    {
        return $this->belongsTo(SkinType::class);
    }

    public function productType()
    {
        return $this->belongsTo(ProductType::class);
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredient');
    }

    public function targetGroups()
    {
        return $this->belongsToMany(TargetGroup::class, 'product_target_group');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    // public function getPrimaryThumbUrlAttribute(): ?string
    // {
    //     $publicId = optional($this->primaryImage)->image_url;

    //     if (! $publicId) {
    //         return null;
    //     }

    //     // ✅ small thumbnail (fast)
    //     // width/height 90, crop fill, auto format, auto quality
    //     return Cloudinary::getUrl($publicId, [
    //         'width' => 90,
    //         'height' => 90,
    //         'crop' => 'fill',
    //         'quality' => 'auto:eco',
    //         'fetch_format' => 'auto',
    //         'secure' => true,
    //     ]);
    // }

}