<?php

namespace App\Observers;

use App\Models\TargetGroup;
use Illuminate\Support\Facades\Cache;

class TargetGroupObserver
{
    public function saved(TargetGroup $model): void
    {
        Cache::forget('products:filters');
        Cache::increment('products:index:version');
    }

    public function deleted(TargetGroup $model): void
    {
        Cache::forget('products:filters');
        Cache::increment('products:index:version');
    }
}