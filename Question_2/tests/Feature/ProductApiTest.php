<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'category_id' => Category::factory()->create()->id,
            'sku' => 'TEST-001',
            'name' => 'Test keyboard',
            'description' => 'Mechanical keyboard',
            'price' => '49.90',
            'stock' => 12,
            'supplier_ids' => Supplier::factory()->count(2)->create()->modelKeys(),
        ], $overrides);
    }

    public function test_creates_product_with_category_and_multiple_suppliers(): void
    {
        $payload = $this->payload();
        $id = $this->postJson('/api/products', $payload)->assertCreated()
            ->assertJsonPath('data.price', '49.90')
            ->assertJsonPath('data.in_stock', true)
            ->assertJsonPath('data.category.id', $payload['category_id'])
            ->assertJsonCount(2, 'data.suppliers')->json('data.id');

        $this->assertDatabaseHas('products', ['id' => $id, 'sku' => 'TEST-001', 'stock' => 12]);
        foreach ($payload['supplier_ids'] as $supplierId) {
            $this->assertDatabaseHas('product_supplier', ['product_id' => $id, 'supplier_id' => $supplierId]);
        }
    }

    public function test_lists_products_with_stable_pagination(): void
    {
        $products = Product::factory()->count(3)->create();
        $this->getJson('/api/products?per_page=2&page=2')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $products[2]->id)
            ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.current_page', 2)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);
    }

    public function test_combines_category_price_and_stock_filters_inclusively(): void
    {
        $category = Category::factory()->create();
        $target = Product::factory()->create(['category_id' => $category->id, 'price' => '10.00', 'stock' => 5]);
        Product::factory()->create(['category_id' => $category->id, 'price' => '9.99', 'stock' => 5]);
        Product::factory()->create(['category_id' => $category->id, 'price' => '20.01', 'stock' => 5]);
        Product::factory()->create(['category_id' => $category->id, 'price' => '10.00', 'stock' => 4]);
        Product::factory()->create(['category_id' => $category->id, 'price' => '10.00', 'stock' => 7]);
        Product::factory()->create(['price' => '10.00', 'stock' => 5]);

        $this->getJson('/api/products?'.http_build_query([
            'category_id' => $category->id, 'min_price' => 10, 'max_price' => 20, 'min_stock' => 5, 'max_stock' => 6,
        ]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
    }

    public function test_zero_price_and_zero_stock_filters_are_not_ignored(): void
    {
        $target = Product::factory()->create(['price' => 0, 'stock' => 0]);
        Product::factory()->create(['price' => 1, 'stock' => 0]);
        Product::factory()->create(['price' => 0, 'stock' => 1]);
        $this->getJson('/api/products?max_price=0&stock=0')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id)
            ->assertJsonPath('data.0.in_stock', false);
    }

    public function test_shows_a_product_with_related_resources(): void
    {
        $product = Product::factory()->hasAttached(Supplier::factory()->count(2))->create();
        $this->getJson("/api/products/{$product->id}")->assertOk()
            ->assertJsonPath('data.id', $product->id)->assertJsonCount(2, 'data.suppliers')
            ->assertJsonPath('data.category.id', $product->category_id);
    }

    public function test_patch_preserves_omitted_fields_and_suppliers(): void
    {
        $product = Product::factory()->hasAttached(Supplier::factory())->create();
        $this->patchJson("/api/products/{$product->id}", ['stock' => 0])->assertOk()
            ->assertJsonPath('data.stock', 0)->assertJsonPath('data.in_stock', false)
            ->assertJsonPath('data.name', $product->name)->assertJsonCount(1, 'data.suppliers');
    }

    public function test_update_replaces_suppliers_and_can_explicitly_clear_them(): void
    {
        $product = Product::factory()->hasAttached(Supplier::factory())->create();
        $payload = $this->payload(['sku' => $product->sku]);
        $this->putJson("/api/products/{$product->id}", $payload)->assertOk()
            ->assertJsonPath('data.name', $payload['name'])->assertJsonCount(2, 'data.suppliers');
        $this->assertEqualsCanonicalizing($payload['supplier_ids'], $product->suppliers()->pluck('suppliers.id')->all());
        $this->patchJson("/api/products/{$product->id}", ['supplier_ids' => []])->assertOk()
            ->assertJsonCount(0, 'data.suppliers');
        $this->assertDatabaseCount('product_supplier', 0);
    }

    public function test_invalid_data_is_rejected_without_writing_a_product(): void
    {
        $this->postJson('/api/products', $this->payload([
            'category_id' => 999999, 'price' => -1, 'stock' => -1,
            'name' => '', 'sku' => 'bad sku', 'supplier_ids' => [999999],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['category_id', 'price', 'stock', 'name', 'sku', 'supplier_ids.0']);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_supplier', 0);
    }

    public function test_rejects_duplicate_sku_and_duplicate_suppliers(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->postJson('/api/products', $this->payload([
            'sku' => $product->sku, 'supplier_ids' => [$supplier->id, $supplier->id],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['sku', 'supplier_ids.0']);
    }

    public function test_rejects_excess_price_precision_and_invalid_partial_update(): void
    {
        $product = Product::factory()->create(['price' => '20.00']);
        $this->patchJson("/api/products/{$product->id}", ['price' => '1.234', 'name' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['price', 'name']);
        $this->assertSame('20.00', $product->fresh()->price);
        $this->putJson("/api/products/{$product->id}", ['stock' => 1])->assertUnprocessable()
            ->assertJsonValidationErrors(['sku', 'name', 'price', 'category_id']);
    }

    public function test_invalid_filters_return_validation_errors(): void
    {
        $this->getJson('/api/products?min_price=20&max_price=10&min_stock=5&max_stock=1&per_page=101&page=0')
            ->assertUnprocessable()->assertJsonValidationErrors(['max_price', 'max_stock', 'per_page', 'page']);
    }

    public function test_deletion_is_soft_and_deleted_products_are_not_accessible(): void
    {
        $product = Product::factory()->hasAttached(Supplier::factory())->create();
        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();
        $this->assertSoftDeleted($product);
        $this->assertDatabaseCount('product_supplier', 1);
        $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/products/{$product->id}")->assertNotFound();
        $this->patchJson("/api/products/{$product->id}", ['stock' => 1])->assertNotFound();
        $this->deleteJson("/api/products/{$product->id}")->assertNotFound();
        $this->postJson('/api/products', $this->payload(['sku' => $product->sku]))
            ->assertUnprocessable()->assertJsonValidationErrors('sku');
    }

    public function test_unknown_product_returns_not_found(): void
    {
        $this->getJson('/api/products/999999')->assertNotFound();
    }

    public function test_reference_endpoints_return_categories_and_suppliers(): void
    {
        Category::factory()->create();
        Supplier::factory()->create();
        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/suppliers')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_seed_data_can_be_loaded_twice_without_duplicates(): void
    {
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseCount('suppliers', 2);
        $this->assertDatabaseCount('products', 3);
        $this->assertDatabaseCount('product_supplier', 6);
    }

    public function test_authenticated_api_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/products')->assertOk();
        }
        $this->getJson('/api/products')->assertStatus(429)->assertHeader('Retry-After');
    }
}
