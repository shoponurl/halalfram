<?php

declare(strict_types=1);

use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\InvoiceController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Storefront home. The rest of the catalog moves from hash routes to real pages in Sprint 02.
Route::view('/', 'home')->name('home');

// Sprint 01 vertical slice: one catch-weight product through order → hold → weight → capture → invoice
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart', [CartController::class, 'add'])->middleware('throttle:30,1')->name('cart.add');
Route::patch('/cart/{product}', [CartController::class, 'update'])->middleware('throttle:30,1')->name('cart.update');
Route::delete('/cart/{product}', [CartController::class, 'remove'])->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
Route::get('/checkout/{order}/pay', [CheckoutController::class, 'pay'])->name('checkout.pay');

Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::get('/orders/{order}/invoice.pdf', [InvoiceController::class, 'show'])->name('orders.invoice');
Route::view('/orders/{order}/balance-paid', 'shop.balance-paid')->name('orders.balance-paid');

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
