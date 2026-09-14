<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\View\View;

final class CategoryController extends Controller
{
    public function index(): View
    {
        return view('shop.categories', [
            'categories' => Category::query()->orderBy('sort_order')->withCount(['products' => fn ($q) => $q->active()])->get(),
        ]);
    }

    public function show(Category $category): View
    {
        return view('shop.category', [
            'category' => $category,
            'products' => $category->products()->active()->orderBy('name')->get(),
        ]);
    }
}
