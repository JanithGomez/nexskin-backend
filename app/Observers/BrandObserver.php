<?php

namespace App\Observers;

use App\Models\Brand;
use Illuminate\Support\Facades\Cache;

class BrandObserver
{
    private function flush(): void
    {
        Cache::forget('products:filters');
        Cache::increment('products:index:version');
    }

    public function saved(Brand $brand): void { $this->flush(); }
    public function deleted(Brand $brand): void { $this->flush(); }
    public function restored(Brand $brand): void { $this->flush(); }
    public function forceDeleted(Brand $brand): void { $this->flush(); }
}