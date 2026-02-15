<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\CheckoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());

/* =========================
   PRODUCTS
   ========================= */

Route::get('/products', [ProductController::class, 'index']);
Route::get('/product-filters', [ProductController::class, 'filters']);
Route::get('/products/by-ids', [ProductController::class, 'byIds']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/related/{id}', [ProductController::class, 'related']);
Route::get('/products/best-sellers', [ProductController::class, 'bestSellers']);

Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])
    ->whereNumber('product')
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->prefix('saved-addresses')->group(function () {
    Route::get('/', [AddressController::class, 'index']);
    Route::post('/', [AddressController::class, 'store']);
    Route::delete('/{id}', [AddressController::class, 'destroy']);
});

/* =========================
   NAVBAR
   ========================= */

Route::get('/navbar', [MenuController::class, 'navbar']);

/* =========================
   CART
   ✅ Guest = session
   ✅ Logged-in = bearer token
   ========================= */

Route::middleware(['cart'])->group(function () {
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{itemId}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);
    Route::delete('/cart', [CartController::class, 'clear']);
    
    Route::post('/checkout', [CheckoutController::class, 'placeOrder']);
});

/* =========================
   AUTH (TOKEN)
   ========================= */

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});