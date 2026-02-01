<?php

namespace App\Observers;

use App\Models\ProductType;
use Illuminate\Support\Facades\Cache;

class ProductTypeObserver
{
    public function saved(ProductType $model): void
    {
        Cache::forget('products:filters');
        Cache::increment('products:index:version');
    }

    public function deleted(ProductType $model): void
    {
        Cache::forget('products:filters');
        Cache::increment('products:index:version');
    }
}