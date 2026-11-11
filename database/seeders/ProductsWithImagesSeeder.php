<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProductsWithImagesSeeder extends Seeder
{
    /**
     * URLs d'images Unsplash pour différentes catégories
     */
    private array $imageUrls = [
        'textile' => [
            'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=800&q=80',
            'https://images.unsplash.com/photo-1445205170230-053b83016050?w=800&q=80',
            'https://images.unsplash.com/photo-1434389677669-e08b4cac3105?w=800&q=80',
        ],
        'agroalimentaire' => [
            'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=800&q=80',
            'https://images.unsplash.com/photo-1542838132-92c53300491e?w=800&q=80',
            'https://images.unsplash.com/photo-1476718406336-bb5a9690ee2a?w=800&q=80',
        ],
        'construction' => [
            'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=800&q=80',
            'https://images.unsplash.com/photo-1581094288338-2314dddb7ece?w=800&q=80',
            'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=800&q=80',
        ],
        'electronique' => [
            'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=800&q=80',
            'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=800&q=80',
            'https://images.unsplash.com/photo-1531297484001-80022131f5a1?w=800&q=80',
        ],
        'beaute' => [
            'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=800&q=80',
            'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?w=800&q=80',
            'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?w=800&q=80',
        ],
        'mobilier' => [
            'https://images.unsplash.com/photo-1503602642458-232111445657?w=800&q=80',
            'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&q=80',
            'https://images.unsplash.com/photo-1538688525198-9b88f6f53126?w=800&q=80',
        ],
        'emballage' => [
            'https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=800&q=80',
            'https://images.unsplash.com/photo-1566576912321-b5818e3646a0?w=800&q=80',
            'https://images.unsplash.com/photo-1589308078059-be1415eab4c3?w=800&q=80',
        ],
        'machines' => [
            'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=800&q=80',
            'https://images.unsplash.com/photo-1563770095-39d468f95c8d?w=800&q=80',
            'https://images.unsplash.com/photo-1581092160562-40aa08e78837?w=800&q=80',
        ],
    ];

    public function run(): void
    {
        $this->command->info('Génération de produits avec images...');

        // Récupérer les vendeurs existants
        $vendors = Vendor::with('user')->get();
        
        if ($vendors->isEmpty()) {
            $this->command->error('Aucun vendeur trouvé. Exécutez d\'abord le DatabaseSeeder principal.');
            return;
        }

        // Récupérer les catégories
        $categories = Category::all();
        
        if ($categories->isEmpty()) {
            $this->command->error('Aucune catégorie trouvée. Exécutez d\'abord le DatabaseSeeder principal.');
            return;
        }

        // Option: nettoyer les produits existants avant de régénérer
        // Désactivé par défaut pour éviter la suppression accidentelle
        // Pour nettoyer, utilisez: php artisan tinker puis exécutez le code de suppression

        // Mapping catégories vers clés d'images
        $categoryImageMap = [
            'Électronique & Composants' => 'electronique',
            'Textile & Confection' => 'textile',
            'Machines & Équipements industriels' => 'machines',
            'Bâtiment & Matériaux de construction' => 'construction',
            'Agroalimentaire & Boissons' => 'agroalimentaire',
            'Beauté & Hygiène' => 'beaute',
            'Mobilier & Décoration' => 'mobilier',
            'Emballage & Impression' => 'emballage',
        ];

        // Données de produits détaillées
        $productsData = $this->getProductsData();

        foreach ($productsData as $i => $data) {
            $vendor = $vendors[$i % $vendors->count()];
            $category = $categories->where('name', $data['category'])->first() ?? $categories->first();
            
            $imageKey = $categoryImageMap[$category->name] ?? 'electronique';
            $images = $this->imageUrls[$imageKey] ?? $this->imageUrls['electronique'];

            $product = Product::create([
                'vendor_id' => $vendor->id,
                'category_id' => $category->id,
                'name' => $data['name'],
                'slug' => str($data['name'])->slug() . '-' . rand(100, 999),
                'description' => $data['description'],
                'unit' => $data['unit'],
                'min_order_qty' => $data['moq'],
                'max_order_qty' => $data['max_moq'] ?? null,
                'stock_qty' => rand(100, 5000),
                'base_price' => $data['price'],
                'discount_percent' => $data['discount'] ?? null,
                'promo_ends_at' => ($data['discount'] ?? null) ? now()->addDays(rand(5, 15)) : null,
                'is_active' => true,
            ]);

            // Créer les paliers de prix
            foreach ($data['tiers'] as $tier) {
                PriceTier::create([
                    'product_id' => $product->id,
                    'min_qty' => $tier['min'],
                    'max_qty' => $tier['max'],
                    'unit_price' => $tier['price'],
                ]);
            }

            // Télécharger et ajouter les images
            $this->addImagesToProduct($product, $images, rand(2, 4));

            $this->command->info("✓ Produit créé: {$product->name}");
        }

        $this->command->info('Génération terminée avec succès!');
    }

    /**
     * Télécharge et ajoute des images à un produit
     */
    private function addImagesToProduct(Product $product, array $imageUrls, int $count): void
    {
        $selectedUrls = array_slice($imageUrls, 0, $count);

        foreach ($selectedUrls as $index => $url) {
            try {
                $response = Http::timeout(30)->get($url);
                
                if ($response->successful()) {
                    $fileName = 'products/' . uniqid() . '_' . $index . '.jpg';
                    Storage::disk('public')->put($fileName, $response->body());
                    
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path' => $fileName,
                        'position' => $index,
                    ]);
                }
            } catch (\Exception $e) {
                $this->command->warn("Impossible de télécharger l'image pour {$product->name}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Données détaillées des produits
     */
    private function getProductsData(): array
    {
        return [
            // Textile & Confection
            [
                'name' => 'Tissu Wax Hollandais Premium - Motif Africain',
                'category' => 'Textile & Confection',
                'description' => 'Tissu wax de haute qualité, 100% coton, motifs originaux imprimés aux Pays-Bas. Idéal pour la confection de tenues traditionnelles et modernes. Résistant aux lavages répétés, couleurs vives durables.',
                'unit' => 'lot (6 yards)',
                'moq' => 10,
                'price' => 15000,
                'tiers' => [
                    ['min' => 10, 'max' => 49, 'price' => 15000],
                    ['min' => 50, 'max' => 199, 'price' => 13500],
                    ['min' => 200, 'max' => null, 'price' => 12000],
                ],
            ],
            [
                'name' => 'T-shirts en Coton Bio - Lot de 50',
                'category' => 'Textile & Confection',
                'description' => 'T-shirts unisexes en coton biologique certifié GOTS. Disponibles en plusieurs tailles (S, M, L, XL, XXL). Couleurs: blanc, noir, marine, gris. Parfait pour uniformes et merchandising.',
                'unit' => 'carton (50 pcs)',
                'moq' => 5,
                'price' => 75000,
                'discount' => 10,
                'tiers' => [
                    ['min' => 5, 'max' => 19, 'price' => 75000],
                    ['min' => 20, 'max' => 49, 'price' => 68000],
                    ['min' => 50, 'max' => null, 'price' => 60000],
                ],
            ],
            [
                'name' => 'Jeans Denim - Lot de 100 pièces',
                'category' => 'Textile & Confection',
                'description' => 'Jeans classique en denim de qualité, coupe droite. Disponible en bleu foncé et noir. Tailles: 28 à 42. Idéal pour boutiques de vêtements et grossistes.',
                'unit' => 'carton (100 pcs)',
                'moq' => 3,
                'price' => 450000,
                'tiers' => [
                    ['min' => 3, 'max' => 9, 'price' => 450000],
                    ['min' => 10, 'max' => 29, 'price' => 420000],
                    ['min' => 30, 'max' => null, 'price' => 380000],
                ],
            ],
            [
                'name' => 'Foulards en Soie - Assortiment couleurs',
                'category' => 'Textile & Confection',
                'description' => 'Foulards en soie naturelle, dimensions 70x70cm. Assortiment de 10 couleurs par lot. Finissage bordures roulées. Luxueux et léger.',
                'unit' => 'lot (10 pcs)',
                'moq' => 15,
                'price' => 85000,
                'tiers' => [
                    ['min' => 15, 'max' => 49, 'price' => 85000],
                    ['min' => 50, 'max' => 149, 'price' => 78000],
                    ['min' => 150, 'max' => null, 'price' => 70000],
                ],
            ],

            // Agroalimentaire & Boissons
            [
                'name' => 'Riz local étuvé - Sac 50kg',
                'category' => 'Agroalimentaire & Boissons',
                'description' => 'Riz local de la vallée de l\'Ouémé, étuvé traditionnellement. Grains longs, non cassés, goût authentique. Certifié sans additifs. Stockage optimal: lieu sec et aéré.',
                'unit' => 'sac (50kg)',
                'moq' => 20,
                'price' => 28000,
                'discount' => 5,
                'tiers' => [
                    ['min' => 20, 'max' => 99, 'price' => 28000],
                    ['min' => 100, 'max' => 499, 'price' => 26000],
                    ['min' => 500, 'max' => null, 'price' => 24000],
                ],
            ],
            [
                'name' => 'Huile de palme rouge - Bidon 20L',
                'category' => 'Agroalimentaire & Boissons',
                'description' => 'Huile de palme rouge 100% naturelle, extraite à froid. Riche en bêta-carotène et vitamine E. Idéale pour la cuisine traditionnelle africaine. Sans conservateurs.',
                'unit' => 'bidon (20L)',
                'moq' => 15,
                'price' => 25000,
                'tiers' => [
                    ['min' => 15, 'max' => 59, 'price' => 25000],
                    ['min' => 60, 'max' => 199, 'price' => 23000],
                    ['min' => 200, 'max' => null, 'price' => 21000],
                ],
            ],
            [
                'name' => 'Gari - Sac 25kg',
                'category' => 'Agroalimentaire & Boissons',
                'description' => 'Gari de manioc de qualité supérieure, grain fins et homogènes. Blanc-jaune, goût naturel. Parfait pour la préparation de garri, eba et kokonte.',
                'unit' => 'sac (25kg)',
                'moq' => 25,
                'price' => 18000,
                'tiers' => [
                    ['min' => 25, 'max' => 99, 'price' => 18000],
                    ['min' => 100, 'max' => 399, 'price' => 16500],
                    ['min' => 400, 'max' => null, 'price' => 15000],
                ],
            ],
            [
                'name' => 'Miel pur d\'acacia - Pot 1kg',
                'category' => 'Agroalimentaire & Boissons',
                'description' => 'Miel pur 100% naturel, récolté dans les forêts du Bénin. Saveur douce et florale, texture onctueuse. Riche en enzymes et antioxydants. Certification apiculteur local.',
                'unit' => 'lot (12 pots)',
                'moq' => 10,
                'price' => 48000,
                'discount' => 15,
                'tiers' => [
                    ['min' => 10, 'max' => 49, 'price' => 48000],
                    ['min' => 50, 'max' => 149, 'price' => 44000],
                    ['min' => 150, 'max' => null, 'price' => 40000],
                ],
            ],

            // Bâtiment & Matériaux de construction
            [
                'name' => 'Ciment CEM II 42.5 - Sac 50kg',
                'category' => 'Bâtiment & Matériaux de construction',
                'description' => 'Ciment Portland composé CEM II 42.5, conforme aux normes internationales. Résistance élevée, prise rapide. Idéal pour construction résidentielle et commerciale.',
                'unit' => 'sac (50kg)',
                'moq' => 50,
                'price' => 4500,
                'tiers' => [
                    ['min' => 50, 'max' => 199, 'price' => 4500],
                    ['min' => 200, 'max' => 999, 'price' => 4200],
                    ['min' => 1000, 'max' => null, 'price' => 3900],
                ],
            ],
            [
                'name' => 'Fer à béton HA 12mm - Barre 12m',
                'category' => 'Bâtiment & Matériaux de construction',
                'description' => 'Fer à béton haute adhérence, diamètre 12mm, longueur 12m. Acier FeE500. Conforme aux normes de construction. Résistance et ductilité optimales.',
                'unit' => 'barre (12m)',
                'moq' => 100,
                'price' => 9500,
                'tiers' => [
                    ['min' => 100, 'max' => 499, 'price' => 9500],
                    ['min' => 500, 'max' => 1999, 'price' => 8800],
                    ['min' => 2000, 'max' => null, 'price' => 8200],
                ],
            ],
            [
                'name' => 'Sable de carrière - Camion 10m³',
                'category' => 'Bâtiment & Matériaux de construction',
                'description' => 'Sable de carrière propre, grain fin à moyen. Idéal pour béton, mortier et enduits. Exempt d\'argile et d\'impuretés. Livraison sur chantier possible.',
                'unit' => 'camion (10m³)',
                'moq' => 5,
                'price' => 85000,
                'tiers' => [
                    ['min' => 5, 'max' => 19, 'price' => 85000],
                    ['min' => 20, 'max' => 49, 'price' => 78000],
                    ['min' => 50, 'max' => null, 'price' => 72000],
                ],
            ],
            [
                'name' => 'Briques rouges - Palette 500',
                'category' => 'Bâtiment & Matériaux de construction',
                'description' => 'Briques de terre cuite rouge, dimensions standard 20x10x5cm. Haute résistance, excellente isolation thermique. Idéales pour murs de façade et cloisons.',
                'unit' => 'palette (500 pcs)',
                'moq' => 10,
                'price' => 125000,
                'tiers' => [
                    ['min' => 10, 'max' => 49, 'price' => 125000],
                    ['min' => 50, 'max' => 149, 'price' => 115000],
                    ['min' => 150, 'max' => null, 'price' => 105000],
                ],
            ],

            // Électronique & Composants
            [
                'name' => 'Chargeurs USB-C Rapides 20W - Carton 100',
                'category' => 'Électronique & Composants',
                'description' => 'Chargeurs USB-C Power Delivery 20W, compatibles avec tous les appareils USB-C. Certification CE et FCC. Charge rapide pour smartphones et tablettes.',
                'unit' => 'carton (100 pcs)',
                'moq' => 5,
                'price' => 320000,
                'discount' => 12,
                'tiers' => [
                    ['min' => 5, 'max' => 19, 'price' => 320000],
                    ['min' => 20, 'max' => 49, 'price' => 290000],
                    ['min' => 50, 'max' => null, 'price' => 260000],
                ],
            ],
            [
                'name' => 'Écouteurs Bluetooth TWS - Carton 50',
                'category' => 'Électronique & Composants',
                'description' => 'Écouteurs sans fil True Wireless Stereo, réduction de bruit active. Autonomie 6h + 24h avec boîtier. Étanche IPX5. Qualité sonore HD.',
                'unit' => 'carton (50 pcs)',
                'moq' => 5,
                'price' => 285000,
                'tiers' => [
                    ['min' => 5, 'max' => 19, 'price' => 285000],
                    ['min' => 20, 'max' => 49, 'price' => 260000],
                    ['min' => 50, 'max' => null, 'price' => 235000],
                ],
            ],
            [
                'name' => 'Câbles HDMI 4K - Lot de 50',
                'category' => 'Électronique & Composants',
                'description' => 'Câbles HDMI High Speed 4K@60Hz, support HDR. Longueur 2m. Connecteurs dorés pour meilleure conductivité. Compatible avec tous les appareils HDMI.',
                'unit' => 'lot (50 pcs)',
                'moq' => 10,
                'price' => 75000,
                'tiers' => [
                    ['min' => 10, 'max' => 49, 'price' => 75000],
                    ['min' => 50, 'max' => 199, 'price' => 68000],
                    ['min' => 200, 'max' => null, 'price' => 62000],
                ],
            ],
            [
                'name' => 'Power Bank 20000mAh - Lot de 20',
                'category' => 'Électronique & Composants',
                'description' => 'Batterie externe 20000mAh, double sortie USB-A + USB-C. Charge rapide 18W. Affichage LED du niveau de charge. Protection contre surcharge.',
                'unit' => 'lot (20 pcs)',
                'moq' => 8,
                'price' => 240000,
                'discount' => 8,
                'tiers' => [
                    ['min' => 8, 'max' => 29, 'price' => 240000],
                    ['min' => 30, 'max' => 79, 'price' => 220000],
                    ['min' => 80, 'max' => null, 'price' => 200000],
                ],
            ],

            // Beauté & Hygiène
            [
                'name' => 'Savon noir artisanal - Carton 24',
                'category' => 'Beauté & Hygiène',
                'description' => 'Savon noir traditionnel 100% naturel, fait à la main. Enrichi en beurre de karité et huile de coco. Nettoyant doux, hydratant. 500g par savon.',
                'unit' => 'carton (24 pcs)',
                'moq' => 10,
                'price' => 32000,
                'tiers' => [
                    ['min' => 10, 'max' => 49, 'price' => 32000],
                    ['min' => 50, 'max' => 149, 'price' => 29000],
                    ['min' => 150, 'max' => null, 'price' => 26000],
                ],
            ],
            [
                'name' => 'Huile de coco vierge - Lot de 12',
                'category' => 'Beauté & Hygiène',
                'description' => 'Huile de coco vierge pressée à froid, 500ml par bouteille. Riche en acides gras et vitamines. Multiples usages: cuisine, soins capillaires, hydratation cutanée.',
                'unit' => 'lot (12 btl)',
                'moq' => 15,
                'price' => 54000,
                'discount' => 10,
                'tiers' => [
                    ['min' => 15, 'max' => 59, 'price' => 54000],
                    ['min' => 60, 'max' => 179, 'price' => 49000],
                    ['min' => 180, 'max' => null, 'price' => 45000],
                ],
            ],
            [
                'name' => 'Shampoing naturel au karité - Lot de 20',
                'category' => 'Beauté & Hygiène',
                'description' => 'Shampoing doux au beurre de karité et extraits de plantes locales. Sans sulfates, sans parabens. 300ml par flacon. Convient à tous types de cheveux.',
                'unit' => 'lot (20 flacons)',
                'moq' => 12,
                'price' => 68000,
                'tiers' => [
                    ['min' => 12, 'max' => 49, 'price' => 68000],
                    ['min' => 50, 'max' => 149, 'price' => 62000],
                    ['min' => 150, 'max' => null, 'price' => 56000],
                ],
            ],
            [
                'name' => 'Crème hydratante au shea - Lot de 15',
                'category' => 'Beauté & Hygiène',
                'description' => 'Crème hydratante visage et corps au beurre de karité pur. Texture riche, absorption rapide. 150ml par pot. Parfum léger naturel. Convient peaux sèches et sensibles.',
                'unit' => 'lot (15 pots)',
                'moq' => 10,
                'price' => 75000,
                'tiers' => [
                    ['min' => 10, 'max' => 39, 'price' => 75000],
                    ['min' => 40, 'max' => 99, 'price' => 68000],
                    ['min' => 100, 'max' => null, 'price' => 62000],
                ],
            ],

            // Mobilier & Décoration
            [
                'name' => 'Chaises de bureau ergonomiques - Lot de 10',
                'category' => 'Mobilier & Décoration',
                'description' => 'Chaises de bureau ergonomiques avec dossier filet, accoudoirs réglables et support lombaire. Assise rembourrée, hauteur ajustable. Roulettes multidirectionnelles.',
                'unit' => 'lot (10 pcs)',
                'moq' => 5,
                'price' => 350000,
                'discount' => 7,
                'tiers' => [
                    ['min' => 5, 'max' => 19, 'price' => 350000],
                    ['min' => 20, 'max' => 49, 'price' => 320000],
                    ['min' => 50, 'max' => null, 'price' => 290000],
                ],
            ],
            [
                'name' => 'Bureaux réglables en hauteur - Lot de 5',
                'category' => 'Mobilier & Décoration',
                'description' => 'Bureaux assis-debout électrique, surface 120x60cm. MDF laqué blanc, cadre métal gris. Hauteur réglable 70-120cm. Passe-câbles intégré.',
                'unit' => 'lot (5 pcs)',
                'moq' => 3,
                'price' => 425000,
                'tiers' => [
                    ['min' => 3, 'max' => 9, 'price' => 425000],
                    ['min' => 10, 'max' => 29, 'price' => 390000],
                    ['min' => 30, 'max' => null, 'price' => 355000],
                ],
            ],
            [
                'name' => 'Canapés 3 places - Lot de 2',
                'category' => 'Mobilier & Décoration',
                'description' => 'Canapés 3 places en tissu déhoussable, coussins inclus. Structure bois robuste, ressorts suspendus. Disponible en gris, beige ou marine. Dimensions: 200x90x85cm.',
                'unit' => 'lot (2 pcs)',
                'moq' => 2,
                'price' => 480000,
                'tiers' => [
                    ['min' => 2, 'max' => 9, 'price' => 480000],
                    ['min' => 10, 'max' => 29, 'price' => 440000],
                    ['min' => 30, 'max' => null, 'price' => 400000],
                ],
            ],
            [
                'name' => 'Lampes LED de bureau - Lot de 20',
                'category' => 'Mobilier & Décoration',
                'description' => 'Lampes de bureau LED avec bras articulé, 3 niveaux de luminosité. Tête rotative 360°, alimentation USB. Design moderne épuré. Économie d\'énergie.',
                'unit' => 'lot (20 pcs)',
                'moq' => 8,
                'price' => 96000,
                'tiers' => [
                    ['min' => 8, 'max' => 39, 'price' => 96000],
                    ['min' => 40, 'max' => 99, 'price' => 88000],
                    ['min' => 100, 'max' => null, 'price' => 80000],
                ],
            ],

            // Emballage & Impression
            [
                'name' => 'Cartons d\'emballage double cannelure - Lot 100',
                'category' => 'Emballage & Impression',
                'description' => 'Cartons en double cannelure, dimensions 40x30x20cm. Résistance élevée, idéal pour envois lourds. Écologique, recyclable. Marquage personnalisable possible.',
                'unit' => 'lot (100 pcs)',
                'moq' => 5,
                'price' => 75000,
                'tiers' => [
                    ['min' => 5, 'max' => 19, 'price' => 75000],
                    ['min' => 20, 'max' => 49, 'price' => 68000],
                    ['min' => 50, 'max' => null, 'price' => 62000],
                ],
            ],
            [
                'name' => 'Sacs plastique biodégradables - Lot 1000',
                'category' => 'Emballage & Impression',
                'description' => 'Sacs plastique biodégradables, dimensions 30x40cm. Épaisseur 50 microns. Certification compostable. Idéal pour commerces et épiceries.',
                'unit' => 'lot (1000 pcs)',
                'moq' => 10,
                'price' => 45000,
                'discount' => 5,
                'tiers' => [
                    ['min' => 10, 'max' => 49, 'price' => 45000],
                    ['min' => 50, 'max' => 199, 'price' => 41000],
                    ['min' => 200, 'max' => null, 'price' => 38000],
                ],
            ],
            [
                'name' => 'Étiquettes autocollantes personnalisées - Rouleau 500',
                'category' => 'Emballage & Impression',
                'description' => 'Étiquettes autocollantes 10x5cm, impression couleur personnalisée. Adhésif permanent, papier qualité. Idéal pour étiquetage produits et logistique.',
                'unit' => 'rouleau (500 pcs)',
                'moq' => 15,
                'price' => 35000,
                'tiers' => [
                    ['min' => 15, 'max' => 59, 'price' => 35000],
                    ['min' => 60, 'max' => 199, 'price' => 32000],
                    ['min' => 200, 'max' => null, 'price' => 29000],
                ],
            ],
            [
                'name' => 'Film étirable palettes - Rouleau 500m',
                'category' => 'Emballage & Impression',
                'description' => 'Film étirable transparent, largeur 50cm, épaisseur 23 microns. Haute résistance, élasticité optimale. Idéal pour palettisation et protection marchandises.',
                'unit' => 'rouleau (500m)',
                'moq' => 8,
                'price' => 28000,
                'tiers' => [
                    ['min' => 8, 'max' => 29, 'price' => 28000],
                    ['min' => 30, 'max' => 79, 'price' => 25500],
                    ['min' => 80, 'max' => null, 'price' => 23000],
                ],
            ],

            // Machines & Équipements industriels
            [
                'name' => 'Groupe électrogène diesel 5kVA',
                'category' => 'Machines & Équipements industriels',
                'description' => 'Groupe électrogène diesel 5kVA, démarrage électrique. Silencieux, économique en carburant. Idéal pour chantiers, événements et secours. Réserve 15L.',
                'unit' => 'pièce',
                'moq' => 1,
                'price' => 750000,
                'tiers' => [
                    ['min' => 1, 'max' => 4, 'price' => 750000],
                    ['min' => 5, 'max' => 9, 'price' => 690000],
                    ['min' => 10, 'max' => null, 'price' => 630000],
                ],
            ],
            [
                'name' => 'Compresseur d\'air 50L - Lot de 3',
                'category' => 'Machines & Équipements industriels',
                'description' => 'Compresseur d\'air à piston, réservoir 50L, pression 8 bar. Puissance 2HP. Idéal pour ateliers, garages et industrie légère. Silencieux et fiable.',
                'unit' => 'lot (3 pcs)',
                'moq' => 2,
                'price' => 520000,
                'tiers' => [
                    ['min' => 2, 'max' => 7, 'price' => 520000],
                    ['min' => 8, 'max' => 19, 'price' => 480000],
                    ['min' => 20, 'max' => null, 'price' => 440000],
                ],
            ],
            [
                'name' => 'Bétonnière 150L - Lot de 5',
                'category' => 'Machines & Équipements industriels',
                'description' => 'Bétonnière électrique 150L, moteur 550W. Cuve robuste, basculante. Idéale pour petits chantiers de construction. Livrée avec truelle et pelle.',
                'unit' => 'lot (5 pcs)',
                'moq' => 2,
                'price' => 380000,
                'discount' => 10,
                'tiers' => [
                    ['min' => 2, 'max' => 7, 'price' => 380000],
                    ['min' => 8, 'max' => 19, 'price' => 350000],
                    ['min' => 20, 'max' => null, 'price' => 320000],
                ],
            ],
            [
                'name' => 'Scie circulaire à table - Lot de 3',
                'category' => 'Machines & Équipements industriels',
                'description' => 'Scie circulaire sur table, lame 255mm, moteur 1500W. Guide de coupe réglable, protection lame. Idéale pour menuiserie et atelier bois.',
                'unit' => 'lot (3 pcs)',
                'moq' => 2,
                'price' => 290000,
                'tiers' => [
                    ['min' => 2, 'max' => 7, 'price' => 290000],
                    ['min' => 8, 'max' => 19, 'price' => 265000],
                    ['min' => 20, 'max' => null, 'price' => 240000],
                ],
            ],
        ];
    }
}
