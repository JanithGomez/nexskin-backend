<?php

namespace App\Observers;

use App\Models\TargetGroup;
use Illuminate\Support\Facades\Cache;

class TargetGroupObserver
{
    private function flush(): void
    {
        Cache::forget('products:filters');
        Cache::increment('products:index:version');
    }

    public function saved(TargetGroup $model): void { $this->flush(); }
    public function deleted(TargetGroup $model): void { $this->flush(); }
    public function restored(TargetGroup $model): void { $this->flush(); }
    public function forceDeleted(TargetGroup $model): void { $this->flush(); }
}