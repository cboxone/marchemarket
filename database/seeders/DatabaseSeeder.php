<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Ce seeder crée un admin `admin@marchemarket.test` et des comptes de
        // démonstration avec le mot de passe « password » : à ne jamais
        // exécuter sur un serveur de production. Pour le forcer malgré tout
        // (environnement de démo public, par exemple) : ALLOW_DEMO_SEED=true.
        if (app()->isProduction() && ! filter_var(env('ALLOW_DEMO_SEED'), FILTER_VALIDATE_BOOL)) {
            $this->command?->error('Seeder de démonstration bloqué en production (comptes avec mot de passe connu). Définissez ALLOW_DEMO_SEED=true pour le forcer.');

            return;
        }

        // Appeler le seeder principal (comptes, catégories, vendeurs, produits de base)
        $this->call([
            // Les seeders seront appelés ici
        ]);

        // Compte admin
        User::create([
            'name' => 'Admin MarcheMarket',
            'email' => 'admin@marchemarket.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        // Catégories principales inspirées d'un catalogue B2B généraliste
        $categories = [
            'Électronique & Composants' => 'cpu',
            'Textile & Confection' => 'shirt',
            'Machines & Équipements industriels' => 'cog',
            'Bâtiment & Matériaux de construction' => 'brick-wall',
            'Agroalimentaire & Boissons' => 'wheat',
            'Beauté & Hygiène' => 'sparkles',
            'Mobilier & Décoration' => 'armchair',
            'Emballage & Impression' => 'package',
        ];

        $categoryModels = collect($categories)->map(
            fn ($icon, $name) => Category::create([
                'name' => $name,
                'slug' => str($name)->slug(),
                'icon' => $icon,
            ])
        )->values();

        // Vendeurs de démonstration
        $vendorsData = [
            ['store_name' => 'Bénin Textile Export', 'city' => 'Cotonou', 'verified' => true, 'trade' => true, 'years' => 6],
            ['store_name' => 'Cotonou Agro Distribution', 'city' => 'Cotonou', 'verified' => true, 'trade' => true, 'years' => 4],
            ['store_name' => 'Sahel BTP Matériaux', 'city' => 'Parakou', 'verified' => true, 'trade' => false, 'years' => 3],
            ['store_name' => 'Golfe Électronique', 'city' => 'Porto-Novo', 'verified' => false, 'trade' => false, 'years' => 1],
        ];

        $vendors = collect($vendorsData)->map(function ($data, $i) {
            $user = User::create([
                'name' => 'Gérant ' . $data['store_name'],
                'email' => 'vendeur' . ($i + 1) . '@marchemarket.test',
                'password' => bcrypt('password'),
                'role' => User::ROLE_VENDOR,
                'company_name' => $data['store_name'],
            ]);

            return Vendor::create([
                'user_id' => $user->id,
                'store_name' => $data['store_name'],
                'slug' => str($data['store_name'])->slug(),
                'description' => 'Fournisseur grossiste basé à ' . $data['city'] . ', spécialisé dans la vente en gros pour professionnels.',
                'country' => 'Bénin',
                'city' => $data['city'],
                'is_verified' => $data['verified'],
                'trade_assurance' => $data['trade'],
                'response_rate' => rand(75, 99),
                'years_active' => $data['years'],
            ]);
        });

        // Client de démonstration
        User::create([
            'name' => 'Client Démo',
            'email' => 'client@marchemarket.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_CUSTOMER,
            'company_name' => 'Épicerie du Marché',
        ]);

        // Comptes de démonstration pré-vérifiés : dans la vraie vie, un
        // utilisateur doit cliquer sur le lien reçu par e-mail (voir
        // MustVerifyEmail sur le modèle User), mais on ne veut pas bloquer
        // la démo pour autant.
        User::query()->update(['email_verified_at' => now()]);

        // Produits de démonstration avec paliers de prix
        $productsData = [
            ['name' => 'Tissu wax imprimé (6 yards)', 'unit' => 'lot', 'moq' => 10, 'price' => 12000, 'cat' => 1],
            ['name' => 'Chemises en coton bio - lot de 12', 'unit' => 'carton', 'moq' => 5, 'price' => 45000, 'cat' => 1],
            ['name' => 'Riz local étuvé 25kg', 'unit' => 'sac', 'moq' => 20, 'price' => 15500, 'cat' => 4],
            ['name' => 'Huile de palme rouge 20L', 'unit' => 'bidon', 'moq' => 15, 'price' => 22000, 'cat' => 4],
            ['name' => 'Ciment CEM II 42.5 (sac 50kg)', 'unit' => 'sac', 'moq' => 50, 'price' => 4200, 'cat' => 3],
            ['name' => 'Fer à béton 12mm (barre 12m)', 'unit' => 'barre', 'moq' => 100, 'price' => 8500, 'cat' => 3],
            ['name' => 'Chargeurs USB-C rapides 20W', 'unit' => 'carton (100 pcs)', 'moq' => 3, 'price' => 350000, 'cat' => 0],
            ['name' => 'Écouteurs sans fil Bluetooth', 'unit' => 'carton (50 pcs)', 'moq' => 5, 'price' => 275000, 'cat' => 0],
            ['name' => 'Savon noir artisanal 500g', 'unit' => 'carton (24 pcs)', 'moq' => 8, 'price' => 28000, 'cat' => 5],
            ['name' => 'Chaises de bureau ergonomiques', 'unit' => 'pièce', 'moq' => 10, 'price' => 32000, 'cat' => 6],
            ['name' => 'Cartons d\'emballage double cannelure', 'unit' => 'lot de 100', 'moq' => 5, 'price' => 65000, 'cat' => 7],
            ['name' => 'Groupe électrogène 5kVA', 'unit' => 'pièce', 'moq' => 1, 'price' => 620000, 'cat' => 2],
        ];

        $products = collect();

        foreach ($productsData as $i => $data) {
            $vendor = $vendors[$i % $vendors->count()];
            $product = Product::create([
                'vendor_id' => $vendor->id,
                'category_id' => $categoryModels[$data['cat']]->id,
                'name' => $data['name'],
                'slug' => str($data['name'])->slug() . '-' . rand(100, 999),
                'description' => "Produit de gros vendu directement par {$vendor->store_name}. Quantité minimale de commande : {$data['moq']} {$data['unit']}. Qualité contrôlée, disponible pour export régional.",
                'unit' => $data['unit'],
                'min_order_qty' => $data['moq'],
                'stock_qty' => rand(200, 5000),
                'base_price' => $data['price'],
                'is_active' => true,
            ]);

            // Paliers de prix dégressifs
            PriceTier::create(['product_id' => $product->id, 'min_qty' => $data['moq'], 'max_qty' => $data['moq'] * 5 - 1, 'unit_price' => $data['price']]);
            PriceTier::create(['product_id' => $product->id, 'min_qty' => $data['moq'] * 5, 'max_qty' => $data['moq'] * 20 - 1, 'unit_price' => round($data['price'] * 0.92)]);
            PriceTier::create(['product_id' => $product->id, 'min_qty' => $data['moq'] * 20, 'max_qty' => null, 'unit_price' => round($data['price'] * 0.85)]);

            $products->push($product);
        }

        // Quelques promotions actives en démo
        $products[2]->update(['discount_percent' => 10, 'promo_ends_at' => now()->addDays(10)]);
        $products[6]->update(['discount_percent' => 15, 'promo_ends_at' => now()->addDays(3)]);

        // Un pack de démonstration (regroupe 2 produits du même vendeur)
        $vendorForPack = $products[2]->vendor;
        $pack = $vendorForPack->packs()->create([
            'name' => 'Pack Épicerie de base',
            'slug' => 'pack-epicerie-de-base',
            'description' => 'Riz et huile de palme réunis dans un seul pack pour approvisionner rapidement une boutique de quartier.',
            'pack_price' => round(($products[2]->base_price * 20 + $products[3]->base_price * 15) * 0.9),
        ]);
        $pack->items()->create(['product_id' => $products[2]->id, 'quantity' => 20]);
        $pack->items()->create(['product_id' => $products[3]->id, 'quantity' => 15]);

        // Avis clients de démonstration
        $client = User::where('email', 'client@marchemarket.test')->first();
        $products->take(4)->each(function (Product $product) use ($client) {
            $product->reviews()->create([
                'user_id' => $client->id,
                'rating' => rand(3, 5),
                'comment' => 'Bon rapport qualité-prix, livraison conforme à la commande.',
            ]);
        });
    }
}
