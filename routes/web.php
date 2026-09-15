<?php

declare(strict_types=1);

use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CategoryController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\InvoiceController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\PayPalReturnController;
use App\Http\Controllers\Shop\PrivacyRequestController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TwilioWebhookController;
use Illuminate\Support\Facades\Route;

// Storefront home (the pixel-ported marketing design). Category and product browsing are real pages.
Route::view('/', 'home')->name('home');

Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart', [CartController::class, 'add'])->middleware('throttle:30,1')->name('cart.add');
Route::patch('/cart/{line}', [CartController::class, 'update'])->middleware('throttle:30,1')->name('cart.update');
Route::delete('/cart/{line}', [CartController::class, 'remove'])->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
Route::get('/checkout/{order}/pay', [CheckoutController::class, 'pay'])->name('checkout.pay');
Route::get('/checkout/{order}/paypal/return', PayPalReturnController::class)->name('checkout.paypal.return');
Route::get('/checkout/{order}/paypal/cancel', [PayPalReturnController::class, 'cancel'])->name('checkout.paypal.cancel');

Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::get('/orders/{order}/invoice.pdf', [InvoiceController::class, 'show'])->name('orders.invoice');
Route::view('/orders/{order}/balance-paid', 'shop.balance-paid')->name('orders.balance-paid');

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::post('/webhooks/twilio/sms', TwilioWebhookController::class)->name('twilio.webhook');

// Guideline ch. 7, S07: legal/compliance pages and the public CCPA request form.
Route::view('/privacy', 'shop.legal.privacy')->name('legal.privacy');
Route::view('/terms', 'shop.legal.terms')->name('legal.terms');
Route::get('/privacy/requests', [PrivacyRequestController::class, 'create'])->name('privacy-requests.create');
Route::post('/privacy/requests', [PrivacyRequestController::class, 'store'])->middleware('throttle:10,1')->name('privacy-requests.store');
