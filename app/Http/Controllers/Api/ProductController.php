<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\SkinType;
use App\Models\TargetGroup;
use App\Models\Category;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    private function productsIndexVersion(): int
    {
        return (int) Cache::rememberForever('products:index:version', fn () => 1);
    }

   public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 12);
        $perPage = max(1, min($perPage, 100)); // safety

        // ✅ NEW: category slug filter
        $categorySlug = $request->get('category_slug');

        // normalize filters to make stable cache keys
        $availability = $request->get('availability');
        $priceMin = $request->filled('price_min') ? (float) $request->price_min : null;
        $priceMax = $request->filled('price_max') ? (float) $request->price_max : null;

        $brandIds = $request->filled('brand_ids')
            ? collect(explode(',', $request->brand_ids))
                ->filter()
                ->map(fn ($v) => (int) $v)
                ->sort()
                ->values()
                ->all()
            : [];

        $productTypeIds = $request->filled('product_type_ids')
            ? collect(explode(',', $request->product_type_ids))
                ->filter()
                ->map(fn ($v) => (int) $v)
                ->sort()
                ->values()
                ->all()
            : [];

        $skinTypeIds = $request->filled('skin_type_ids')
            ? collect(explode(',', $request->skin_type_ids))
                ->filter()
                ->map(fn ($v) => (int) $v)
                ->sort()
                ->values()
                ->all()
            : [];

        $targetGroupIds = $request->filled('target_group_ids')
            ? collect(explode(',', $request->target_group_ids))
                ->filter()
                ->map(fn ($v) => (int) $v)
                ->sort()
                ->values()
                ->all()
            : [];

        $page = max(1, (int) $request->get('page', 1));
        $ver = $this->productsIndexVersion();

        // build a stable cache key
        $keyPayload = [
            'v' => $ver,
            'page' => $page,
            'perPage' => $perPage,

            // ✅ NEW: category slug in cache key
            'categorySlug' => $categorySlug,

            'availability' => $availability,
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
            'brandIds' => $brandIds,
            'productTypeIds' => $productTypeIds,
            'skinTypeIds' => $skinTypeIds,
            'targetGroupIds' => $targetGroupIds,
        ];

        $cacheKey = 'products:index:' . md5(json_encode($keyPayload));

        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use (
            $request,
            $perPage,
            $categorySlug,
            $availability,
            $priceMin,
            $priceMax,
            $brandIds,
            $productTypeIds,
            $skinTypeIds,
            $targetGroupIds
        ) {
            $query = Product::with([
                'images',        // used only to pick hover
                'primaryImage',  // used to pick primary
                'brand',
                'productType',
                'targetGroups',
                'skinType',
                'category',      // ✅ required for category_slug filter
            ])->where('is_active', true);

            // ✅ Category filter (self + children + grandchildren)
            if ($categorySlug) {
                $cat = \App\Models\Category::with(['children.children'])
                    ->where('slug', $categorySlug)
                    ->first();

                if ($cat) {
                    $ids = collect([$cat->id])
                        ->merge($cat->children->pluck('id'))
                        ->merge($cat->children->flatMap(fn ($c) => $c->children->pluck('id')))
                        ->unique()
                        ->values()
                        ->all();

                    $query->whereIn('category_id', $ids);
                } else {
                    // slug not found => return none
                    $query->whereRaw('1=0');
                }
            }

            // Availability
            if ($availability) {
                match ($availability) {
                    'in_stock' => $query->where('stock', '>', 0),
                    'out_of_stock' => $query->where('stock', '<=', 0),
                    default => null,
                };
            }

            // Price range
            if (!is_null($priceMin)) {
                $query->where('price', '>=', $priceMin);
            }
            if (!is_null($priceMax)) {
                $query->where('price', '<=', $priceMax);
            }

            // Brand filter
            if (!empty($brandIds)) {
                $query->whereIn('brand_id', $brandIds);
            }

            // Product type filter
            if (!empty($productTypeIds)) {
                $query->whereIn('product_type_id', $productTypeIds);
            }

            // Skin type filter
            if (!empty($skinTypeIds)) {
                $query->whereIn('skin_type_id', $skinTypeIds);
            }

            // Target groups (many-to-many)
            if (!empty($targetGroupIds)) {
                $query->whereHas('targetGroups', function ($q) use ($targetGroupIds) {
                    $q->whereIn('target_groups.id', $targetGroupIds);
                });
            }

            // Stable order
            $query->orderByDesc('id');

            $paginator = $query
                ->paginate($perPage)
                ->appends($request->query())
                ->through(function ($product) {
                    $primaryImage = $product->primaryImage;

                    $hoverImage = $product->images
                        ->where('id', '!=', optional($primaryImage)->id)
                        ->first();

                    return [
                        'id' => $product->id,
                        'title' => $product->name,
                        'slug' => $product->slug,
                        'short_description' => $product->short_description,
                        'price' => (float) $product->price,
                        'soldOut' => $product->stock <= 0,

                        // ✅ FAST: public_id only
                        'imgPublicId' => $primaryImage?->image_url,
                        'hoverPublicId' => $hoverImage?->image_url,
                    ];
                });

            return [
                'data' => $paginator->getCollection()->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'links' => [
                    'next' => $paginator->nextPageUrl(),
                    'prev' => $paginator->previousPageUrl(),
                ],
            ];
        });

        return response()->json($data);
    }

    public function filters()
    {
        $data = Cache::remember('products:filters', now()->addHours(12), function () {
            return [
                'brands' => Brand::query()
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])
                    ->values()
                    ->all(),

                'productTypes' => ProductType::query()
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])
                    ->values()
                    ->all(),

                'skinTypes' => SkinType::query()
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
                    ->values()
                    ->all(),

                'targetGroups' => TargetGroup::query()
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])
                    ->values()
                    ->all(),
            ];
        });

        return response()->json($data);
    }

    public function show(Request $request, int $id)
    {
        $cacheKey = "products:show:{$id}";

        $data = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($id) {
            $product = Product::with([
                'images',
                'primaryImage',
                'brand',
                'category',
                'productType',
                'targetGroups',
                'skinType',
                'ingredients',
                'reviews' => fn ($q) => $q->where('is_approved', 1)->latest(),
            ])
                ->where('is_active', true)
                ->findOrFail($id);

            $primaryImage = $product->primaryImage;

            $hoverImage = $product->images
                ->where('id', '!=', optional($primaryImage)->id)
                ->first();

            $gallery = $product->images->values()->map(function ($img) {
                return [
                    'id' => $img->id,
                    'is_primary' => (bool) $img->is_primary,
                    'sort' => (int) $img->sort,
                    'publicId' => $img->image_url,
                ];
            })->values()->all();

            $reviews = $product->reviews->map(function ($r) {
                $displayName = $r->is_anonymous
                    ? 'Anonymous'
                    : (optional($r->user)->name ?? $r->guest_name ?? 'Customer');

                return [
                    'id' => $r->id,
                    'name' => $displayName,
                    'rating' => (int) ($r->rating ?? 0),
                    'title' => $r->review_title,
                    'comment' => $r->comment,
                    'media' => $r->media ?? [],
                    'created_at' => optional($r->created_at)?->toISOString(),
                ];
            })->values()->all();

            $avgRating = count($reviews)
                ? round(collect($reviews)->avg('rating'), 1)
                : 0;

            return [
                'product' => [
                    'id' => $product->id,
                    'title' => $product->name,
                    'slug' => $product->slug,

                    'short_description' => $product->short_description,
                    'description' => $product->description,
                    'benefits' => $product->benefits,
                    'how_to_use' => $product->how_to_use,

                    'price' => (float) $product->price,
                    'stock' => (int) $product->stock,
                    'soldOut' => $product->stock <= 0,
                    'is_active' => (bool) $product->is_active,

                    'imgPublicId' => $primaryImage?->image_url,
                    'hoverPublicId' => $hoverImage?->image_url,

                    'gallery' => $gallery,

                    'brand' => $product->brand ? [
                        'id' => $product->brand->id,
                        'name' => $product->brand->name,
                        'slug' => $product->brand->slug ?? null,
                    ] : null,

                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'slug' => $product->category->slug ?? null,
                    ] : null,

                    'productType' => $product->productType ? [
                        'id' => $product->productType->id,
                        'name' => $product->productType->name,
                        'slug' => $product->productType->slug ?? null,
                    ] : null,

                    'skinType' => $product->skinType ? [
                        'id' => $product->skinType->id,
                        'name' => $product->skinType->name,
                        'slug' => $product->skinType->slug ?? null,
                    ] : null,

                    'targetGroups' => $product->targetGroups->map(fn ($tg) => [
                        'id' => $tg->id,
                        'name' => $tg->name,
                        'slug' => $tg->slug ?? null,
                    ])->values()->all(),

                    'ingredients' => $product->ingredients->map(fn ($ing) => [
                        'id' => $ing->id,
                        'name' => $ing->name,
                    ])->values()->all(),

                    'reviews' => [
                        'avg_rating' => $avgRating,
                        'count' => count($reviews),
                        'items' => $reviews,
                    ],
                ],
            ];
        });

        return response()->json($data);
    }

    public function byIds(Request $request)
    {
        $ids = collect(explode(',', (string) $request->get('ids')))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $cacheKey = 'products:byids:' . md5($ids->implode(','));

        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($ids) {
            $products = Product::with(['images', 'primaryImage'])
                ->where('is_active', true)
                ->whereIn('id', $ids)
                ->get()
                ->sortBy(fn ($p) => $ids->search($p->id))
                ->values()
                ->map(function ($product) {
                    $primaryImage = $product->primaryImage;

                    $hoverImage = $product->images
                        ->where('id', '!=', optional($primaryImage)->id)
                        ->first();

                    return [
                        'id' => $product->id,
                        'title' => $product->name,
                        'slug' => $product->slug,
                        'short_description' => $product->short_description,
                        'price' => (float) $product->price,
                        'soldOut' => $product->stock <= 0,
                        'imgPublicId' => $primaryImage?->image_url,
                        'hoverPublicId' => $hoverImage?->image_url,
                    ];
                })
                ->all();

            return ['data' => $products];
        });

        return response()->json($data);
    }

    public function related(Request $request, int $id)
    {
        $limit = max(1, min((int) $request->get('limit', 8), 20));
        $cacheKey = "products:related:{$id}:{$limit}";

        $data = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($id, $limit) {
            $product = Product::where('is_active', true)->findOrFail($id);

            $query = Product::with(['images', 'primaryImage'])
                ->where('is_active', true)
                ->where('id', '!=', $product->id);

            if ($product->product_type_id) {
                $query->where('product_type_id', $product->product_type_id);
            } elseif ($product->brand_id) {
                $query->where('brand_id', $product->brand_id);
            }

            $items = $query->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(function ($product) {
                    $primaryImage = $product->primaryImage;

                    $hoverImage = $product->images
                        ->where('id', '!=', optional($primaryImage)->id)
                        ->first();

                    return [
                        'id' => $product->id,
                        'title' => $product->name,
                        'slug' => $product->slug,
                        'short_description' => $product->short_description,
                        'price' => (float) $product->price,
                        'soldOut' => $product->stock <= 0,
                        'imgPublicId' => $primaryImage?->image_url,
                        'hoverPublicId' => $hoverImage?->image_url,
                    ];
                })
                ->values()
                ->all();

            return ['data' => $items];
        });

        return response()->json($data);
    }
}