<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Pack;
use App\Models\Product;
use App\Models\Vendor;

class HomeController extends Controller
{
    public function index()
    {
        $categories = Category::topLevel();

        $featuredProducts = Product::query()
            ->with(['images', 'vendor'])
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('discount_percent', '>', 0)
                      ->where(function ($q) {
                          $q->whereNull('promo_ends_at')
                            ->orWhere('promo_ends_at', '>', now());
                      });
            })
            ->inRandomOrder()
            ->take(8)
            ->get();

        if ($featuredProducts->isEmpty()) {
            $featuredProducts = Product::query()
                ->with(['images', 'vendor'])
                ->where('is_active', true)
                ->inRandomOrder()
                ->take(8)
                ->get();
        }

        $backgroundImages = Product::query()
            ->with('images')
            ->where('is_active', true)
            ->whereHas('images')
            ->inRandomOrder()
            ->take(6)
            ->get();

        $packs = Pack::query()
            ->where('is_active', true)
            ->with('items.product', 'vendor')
            ->latest()
            ->take(3)
            ->get();

        $topVendors = Vendor::query()
            ->where('is_verified', true)
            ->withCount('products')
            ->orderByDesc('response_rate')
            ->take(4)
            ->get();

        return view('home', compact('categories', 'featuredProducts', 'packs', 'topVendors', 'backgroundImages'));
    }
}
