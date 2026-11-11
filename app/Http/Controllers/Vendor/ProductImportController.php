<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportController extends Controller
{
    /**
     * Colonnes attendues dans le fichier CSV, dans cet ordre. Une ligne
     * d'en-tête est ignorée si elle est détectée (voir isHeaderRow()).
     */
    private const COLUMNS = ['name', 'category', 'unit', 'min_order_qty', 'stock_qty', 'base_price', 'description'];

    public function create()
    {
        return view('vendor.products.import');
    }

    public function template()
    {
        $csv = implode(',', self::COLUMNS) . "\n"
            . 'Riz étuvé 25kg,Agroalimentaire & Boissons,sac,20,500,15000,"Riz local étuvé, sac de 25kg"' . "\n"
            . 'Chaises de bureau,Mobilier & Décoration,pièce,10,100,32000,Chaise ergonomique pour bureau' . "\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="modele-produits-marchemarket.csv"',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $vendor = $request->user()->vendor;
        $categories = Category::all()->keyBy(fn (Category $c) => Str::lower($c->name));

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $lineNumber = 0;
        $created = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($row === [null] || $row === false) {
                continue; // ligne vide
            }

            if ($lineNumber === 1 && $this->isHeaderRow($row)) {
                continue;
            }

            $data = array_combine(self::COLUMNS, array_pad(array_slice($row, 0, count(self::COLUMNS)), count(self::COLUMNS), null));

            $rowErrors = $this->validateRow($data, $categories);

            if ($rowErrors) {
                $errors[] = __('Ligne :line', ['line' => $lineNumber]) . ' : ' . implode(' ', $rowErrors);

                continue;
            }

            DB::transaction(function () use ($data, $categories, $vendor, &$created) {
                $category = $categories[Str::lower(trim($data['category']))];

                $product = $vendor->products()->create([
                    'category_id' => $category->id,
                    'name' => trim($data['name']),
                    'slug' => str(trim($data['name']))->slug() . '-' . uniqid(),
                    'description' => $data['description'] ? trim($data['description']) : null,
                    'unit' => trim($data['unit']),
                    'min_order_qty' => (int) $data['min_order_qty'],
                    'stock_qty' => (int) $data['stock_qty'],
                    'base_price' => (float) $data['base_price'],
                    'is_active' => true,
                ]);

                PriceTier::create([
                    'product_id' => $product->id,
                    'min_qty' => (int) $data['min_order_qty'],
                    'max_qty' => null,
                    'unit_price' => (float) $data['base_price'],
                ]);

                $created++;
            });
        }

        fclose($handle);

        return back()->with([
            'status' => trans_choice(':count produit importé avec succès.|:count produits importés avec succès.', $created, ['count' => $created]),
            'import_errors' => $errors,
        ]);
    }

    /**
     * @return array<int, string> liste des erreurs de validation pour cette ligne, vide si la ligne est valide
     */
    private function validateRow(array $data, $categories): array
    {
        $errors = [];

        if (empty(trim((string) $data['name']))) {
            $errors[] = __('nom manquant.');
        }

        if (empty($data['category']) || ! $categories->has(Str::lower(trim($data['category'])))) {
            $errors[] = __("catégorie « :category » introuvable.", ['category' => $data['category'] ?? '']);
        }

        if (empty(trim((string) $data['unit']))) {
            $errors[] = __('unité manquante.');
        }

        if (! is_numeric($data['min_order_qty']) || (int) $data['min_order_qty'] < 1) {
            $errors[] = __('quantité minimale invalide.');
        }

        if (! is_numeric($data['stock_qty']) || (int) $data['stock_qty'] < 0) {
            $errors[] = __('stock invalide.');
        }

        if (! is_numeric($data['base_price']) || (float) $data['base_price'] <= 0) {
            $errors[] = __('prix de base invalide.');
        }

        return $errors;
    }

    private function isHeaderRow(array $row): bool
    {
        return isset($row[0]) && Str::lower(trim($row[0])) === 'name';
    }
}
