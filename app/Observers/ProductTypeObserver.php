<?php

namespace App\Observers;

use App\Models\ProductType;
use Illuminate\Support\Facades\Cache;

class ProductTypeObserver
{
    private function flush(): void
    {
        Cache::forget('products:filters');
        Cache::increment('products:index:version');
    }

    public function saved(ProductType $model): void { $this->flush(); }
    public function deleted(ProductType $model): void { $this->flush(); }
    public function restored(ProductType $model): void { $this->flush(); }
    public function forceDeleted(ProductType $model): void { $this->flush(); }
}