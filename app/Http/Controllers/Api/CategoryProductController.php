<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryProductController extends Controller
{
    public function index(Request $request, string $slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        // Include products from child categories if needed
        $categoryIds = $category->children()->pluck('id')->push($category->id);

        $products = Product::with([
            'images',
            'brand',
            'productType',
        ])
            ->whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->paginate(12);

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ],
            'products' => $products->through(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'brand' => $product->brand?->name,
                'type' => $product->productType?->name,
                'image' => $product->images
                    ->where('is_primary', true)
                    ->first()?->image_url
                    ?? $product->images->first()?->image_url,
            ]),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }
}
