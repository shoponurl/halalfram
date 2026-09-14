<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Storefront home. Product, checkout and account views are hash routes inside the page until Sprint 01–02.
Route::view('/', 'home')->name('home');
