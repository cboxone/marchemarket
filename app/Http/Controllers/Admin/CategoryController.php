<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')->orderBy('name')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
            'icon' => ['nullable', 'string', 'max:60'],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ], [
            'name.unique' => __('Une catégorie portant ce nom existe déjà.'),
        ]);

        $category = Category::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
        ]);

        Category::flushTopLevelCache();

        AuditLog::record('category.created', 'Catégorie créée : ' . $category->name, $category);

        return back()->with('status', __('Catégorie créée.'));
    }

    public function destroy(Category $category)
    {
        // Supprimer une catégorie ne doit jamais emporter les produits des
        // vendeurs avec elle : on refuse tant qu'elle n'est pas vide.
        if (($productsCount = $category->products()->count()) > 0) {
            return back()->withErrors(['category' => __('Suppression impossible : cette catégorie contient encore :count produit(s).', ['count' => $productsCount])]);
        }

        if (($childrenCount = $category->children()->count()) > 0) {
            return back()->withErrors(['category' => __('Suppression impossible : cette catégorie contient :count sous-catégorie(s).', ['count' => $childrenCount])]);
        }

        $name = $category->name;
        $category->delete();

        Category::flushTopLevelCache();

        AuditLog::record('category.deleted', 'Catégorie supprimée : ' . $name);

        return back()->with('status', __('Catégorie supprimée.'));
    }

    /**
     * Le slug est unique en base : deux noms proches (« Café » / « Cafe »)
     * donnaient le même slug et une erreur 500 à l'enregistrement.
     */
    private function uniqueSlug(string $name): string
    {
        $base = str($name)->slug()->toString() ?: 'categorie';
        $slug = $base;
        $i = 2;

        while (Category::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
