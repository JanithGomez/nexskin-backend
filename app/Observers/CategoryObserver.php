<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        Cache::forget('menu:navbar');
    }

    public function deleted(Category $category): void
    {
        Cache::forget('menu:navbar');
    }

    public function restored(Category $category): void
    {
        Cache::forget('menu:navbar');
    }

    public function forceDeleted(Category $category): void
    {
        Cache::forget('menu:navbar');
    }
}