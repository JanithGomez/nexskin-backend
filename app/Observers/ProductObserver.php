<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    private function flush(Product $product): void
    {
        Cache::forget("products:show:{$product->id}");

        // invalidate all product list caches (index + byIds if you version it)
        Cache::increment('products:index:version');

        // optional: clear some known related limits
        foreach ([4, 6, 8, 12, 16, 20] as $limit) {
            Cache::forget("products:related:{$product->id}:{$limit}");
        }
    }

    public function saved(Product $product): void { $this->flush($product); }
    public function deleted(Product $product): void { $this->flush($product); }
    public function restored(Product $product): void { $this->flush($product); }
    public function forceDeleted(Product $product): void { $this->flush($product); }
}