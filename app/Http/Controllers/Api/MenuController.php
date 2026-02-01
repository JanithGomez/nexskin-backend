<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class MenuController extends Controller
{
    public function navbar()
    {
        $data = Cache::remember('menu:navbar', now()->addHours(12), function () {
            $categories = Category::whereNull('parent_id')
                ->with(['children.children'])
                ->orderBy('name')
                ->get();

            return $categories->map(fn ($level1) => [
                'label' => $level1->name,
                'slug'  => $level1->slug,
                'href'  => "/category/{$level1->slug}", // ✅ NEW: level 1 link

                'menu'  => $level1->children->map(fn ($level2) => [
                    'heading' => $level2->name,
                    'slug'    => $level2->slug,
                    'href'    => "/category/{$level2->slug}", // ✅ NEW: level 2 link

                    'links'   => $level2->children->map(fn ($level3) => [
                        'text' => $level3->name,
                        'slug' => $level3->slug,
                        'href' => "/category/{$level3->slug}", // level 3 link
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all();
        });

        return response()->json($data);
    }
}