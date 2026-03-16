<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\SkinType;
use App\Models\TargetGroup;
use App\Models\Review;

use App\Observers\BrandObserver;
use App\Observers\CategoryObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductTypeObserver;
use App\Observers\SkinTypeObserver;
use App\Observers\TargetGroupObserver;
use App\Observers\ReviewObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Category::observe(CategoryObserver::class);

        Product::observe(ProductObserver::class);

        Brand::observe(BrandObserver::class);
        ProductType::observe(ProductTypeObserver::class);
        SkinType::observe(SkinTypeObserver::class);
        TargetGroup::observe(TargetGroupObserver::class);
        Review::observe(ReviewObserver::class);
    }
}