<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Pack;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'priority' => '1.0'],
            ['loc' => route('products.index'), 'priority' => '0.9'],
            ['loc' => route('packs.index'), 'priority' => '0.7'],
        ]);

        Category::topLevel()->each(function (Category $category) use ($urls) {
            $urls->push(['loc' => route('products.index', ['categorie' => $category->slug]), 'priority' => '0.6']);
        });

        Product::where('is_active', true)->get(['slug', 'updated_at'])->each(function (Product $product) use ($urls) {
            $urls->push([
                'loc' => route('products.show', $product),
                'lastmod' => $product->updated_at->toAtomString(),
                'priority' => '0.8',
            ]);
        });

        Pack::where('is_active', true)->get(['slug', 'updated_at'])->each(function (Pack $pack) use ($urls) {
            $urls->push([
                'loc' => route('packs.show', $pack),
                'lastmod' => $pack->updated_at->toAtomString(),
                'priority' => '0.6',
            ]);
        });

        Vendor::where('is_verified', true)->get(['slug', 'updated_at'])->each(function (Vendor $vendor) use ($urls) {
            $urls->push([
                'loc' => route('vendors.show', $vendor),
                'lastmod' => $vendor->updated_at->toAtomString(),
                'priority' => '0.5',
            ]);
        });

        $xml = view('seo.sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $content = "User-agent: *\n"
            . "Disallow: /panier\n"
            . "Disallow: /commande\n"
            . "Disallow: /mes-commandes\n"
            . "Disallow: /messages\n"
            . "Disallow: /profil\n"
            . "Disallow: /vendeur\n"
            . "Disallow: /admin\n"
            . "Allow: /\n\n"
            . 'Sitemap: ' . route('seo.sitemap') . "\n";

        return response($content, 200)->header('Content-Type', 'text/plain');
    }
}
