<?php

namespace App\Observers;

use App\Models\Review;
use Illuminate\Support\Facades\Cache;

class ReviewObserver
{
    private function flush(Review $review): void
    {
        // ✅ Product page cache includes reviews
        Cache::forget("products:show:{$review->product_id}");

        // Optional: if you cache related items and want fresh ratings etc.
        // Cache::forget("products:related:{$review->product_id}:8");
        // You can add more limits if you use other limits.
    }

    public function created(Review $review): void
    {
        $this->flush($review);
    }

    public function updated(Review $review): void
    {
        $this->flush($review);
    }

    public function deleted(Review $review): void
    {
        $this->flush($review);
    }

    public function restored(Review $review): void
    {
        $this->flush($review);
    }

    public function forceDeleted(Review $review): void
    {
        $this->flush($review);
    }
}