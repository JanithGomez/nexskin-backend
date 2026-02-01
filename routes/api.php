<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/products', [ProductController::class, 'index']);
Route::get('/product-filters', [ProductController::class, 'filters']);
Route::get('/products/by-ids', [ProductController::class, 'byIds']);
Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])->whereNumber('product')->middleware('throttle:5,1'); // 5 reviews per minute per IP;
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/related/{id}', [ProductController::class, 'related']);

Route::get('/cart', [CartController::class, 'show']);
Route::post('/cart/items', [CartController::class, 'addItem']);
Route::patch('/cart/items/{itemId}', [CartController::class, 'updateItem']);
Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);

Route::get('/navbar', [\App\Http\Controllers\Api\MenuController::class, 'navbar']);

// Route::get('/categories/{slug}/products',[\App\Http\Controllers\Api\CategoryProductController::class, 'index']);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});