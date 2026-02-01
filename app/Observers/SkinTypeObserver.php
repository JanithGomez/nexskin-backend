<?php

namespace App\Observers;

use App\Models\SkinType;
use Illuminate\Support\Facades\Cache;

class SkinTypeObserver
{
    public function saved(SkinType $model): void
    {
        Cache::forget('products:filters');
        Cache::increment('products:index:version');
    }

    public function deleted(SkinType $model): void
    {
        Cache::forget('products:filters');
        Cache::increment('products:index:version');
    }
}