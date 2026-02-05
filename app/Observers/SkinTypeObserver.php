<?php

namespace App\Observers;

use App\Models\SkinType;
use Illuminate\Support\Facades\Cache;

class SkinTypeObserver
{
    private function flush(): void
    {
        Cache::forget('products:filters');
        Cache::increment('products:index:version');
    }

    public function saved(SkinType $model): void { $this->flush(); }
    public function deleted(SkinType $model): void { $this->flush(); }
    public function restored(SkinType $model): void { $this->flush(); }
    public function forceDeleted(SkinType $model): void { $this->flush(); }
}