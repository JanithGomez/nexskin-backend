<?php

namespace App\Observers;

use App\Models\Brand;
use Illuminate\Support\Facades\Cache;

class BrandObserver
{
    public function saved(Brand $brand): void
    {
        Cache::forget('products:filters');
        Cache::increment('products:index:version');
    }

    public function deleted(Brand $brand): void
    {
        Cache::forget('products:filters');
        Cache::increment('products:index:version');
    }
}