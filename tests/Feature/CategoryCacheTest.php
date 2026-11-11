<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_level_returns_only_top_level_categories_ordered_by_name(): void
    {
        Category::factory()->create(['name' => 'Zoo']);
        Category::factory()->create(['name' => 'Agro']);
        $parent = Category::factory()->create(['name' => 'Parent']);
        Category::factory()->create(['name' => 'Enfant', 'parent_id' => $parent->id]);

        $names = Category::topLevel()->pluck('name')->all();

        $this->assertSame(['Agro', 'Parent', 'Zoo'], $names);
    }

    public function test_the_cache_is_invalidated_when_an_admin_creates_a_category(): void
    {
        // Peuple le cache avec l'état "avant".
        $this->assertCount(0, Category::topLevel());

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Nouvelle catégorie']);

        // Sans invalidation, ceci renverrait encore la liste vide mise en
        // cache par l'appel précédent.
        $this->assertCount(1, Category::topLevel());
    }

    public function test_the_cache_is_invalidated_when_an_admin_deletes_a_category(): void
    {
        $category = Category::factory()->create();
        $this->assertCount(1, Category::topLevel());

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category));

        $this->assertCount(0, Category::topLevel());
    }
}
