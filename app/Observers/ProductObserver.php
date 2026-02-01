<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    public function saved(Product $product): void
    {
        Cache::forget("products:show:{$product->id}");

        // related caches: easiest is to forget common limits OR just bump version
        Cache::increment('products:index:version');

        // Optionally clear some known related limits
        foreach ([4, 6, 8, 12, 16, 20] as $limit) {
            Cache::forget("products:related:{$product->id}:{$limit}");
        }
    }

    public function deleted(Product $product): void
    {
        Cache::forget("products:show:{$product->id}");
        Cache::increment('products:index:version');

        foreach ([4, 6, 8, 12, 16, 20] as $limit) {
            Cache::forget("products:related:{$product->id}:{$limit}");
        }
    }

    public function restored(Product $product): void
    {
        Cache::forget("products:show:{$product->id}");
        Cache::increment('products:index:version');
    }

    public function forceDeleted(Product $product): void
    {
        Cache::forget("products:show:{$product->id}");
        Cache::increment('products:index:version');
    }
}