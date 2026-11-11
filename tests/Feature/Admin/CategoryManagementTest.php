<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_category_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($category);
    }

    public function test_a_category_with_products_cannot_be_deleted_and_its_products_survive(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category))
            ->assertSessionHasErrors('category');

        $this->assertModelExists($category);
        $this->assertModelExists($product);
    }

    public function test_a_category_with_subcategories_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $parent))
            ->assertSessionHasErrors('category');

        $this->assertModelExists($parent);
        $this->assertSame($parent->id, $child->fresh()->parent_id);
    }

    public function test_the_model_itself_refuses_to_delete_a_category_that_still_has_products(): void
    {
        // Garde-fou hors back-office (tinker, script) : la clé étrangère en
        // cascade ne doit jamais pouvoir effacer les produits.
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertFalse($category->delete());

        $this->assertModelExists($category);
        $this->assertModelExists($product);
    }

    public function test_creating_a_category_with_an_existing_name_is_rejected_cleanly(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->create(['name' => 'Textile']);

        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Textile'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Category::where('name', 'Textile')->count());
    }

    public function test_names_that_produce_the_same_slug_get_distinct_slugs(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Café'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Cafe'])->assertSessionHasNoErrors();

        $slugs = Category::pluck('slug')->all();

        $this->assertCount(2, $slugs);
        $this->assertCount(2, array_unique($slugs));
    }
}
