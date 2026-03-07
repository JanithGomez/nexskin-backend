<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Support\EmailHelper;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review_title' => ['nullable', 'string', 'max:140'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'guest_name' => ['nullable', 'string', 'max:100'],
            'guest_email' => ['nullable', 'email:rfc,dns', 'indisposable', 'max:150'],
            'is_anonymous' => ['nullable', 'boolean'],

            'media' => ['nullable', 'array', 'max:5'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:10240'],
        ]);

        $emailSuggestion = EmailHelper::suggest($validated['guest_email'] ?? null);

        if ($emailSuggestion) {
            return response()->json([
                'message' => 'Please confirm your email address.',
                'field' => 'guest_email',
                'email_suggestion' => $emailSuggestion,
            ], 422);
        }

        $user = $request->user();
        $isAnonymous = (bool) ($validated['is_anonymous'] ?? false);

        if (! $user && ! $isAnonymous && empty($validated['guest_name'])) {
            return response()->json([
                'message' => 'Name is required unless you post as anonymous.',
            ], 422);
        }

        $mediaUrls = [];

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $uploaded = Cloudinary::upload($file->getRealPath(), [
                    'folder' => 'reviews',
                    'resource_type' => 'auto',
                ]);

                $mediaUrls[] = $uploaded->getSecurePath();
            }
        }

        $review = Review::create([
            'user_id' => $user?->id,
            'product_id' => $product->id,
            'rating' => (int) $validated['rating'],
            'review_title' => $validated['review_title'] ?? null,
            'comment' => $validated['comment'] ?? null,
            'media' => $mediaUrls ?: null,

            'guest_name' => $user ? null : ($validated['guest_name'] ?? null),
            'guest_email' => $user ? null : ($validated['guest_email'] ?? null),

            'is_anonymous' => $isAnonymous,
            'is_approved' => false,
        ]);

        return response()->json([
            'message' => 'Review submitted. It will appear after admin approval.',
            'review_id' => $review->id,
        ], 201);
    }
}