<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_repeated_lists_skip_product_queries_and_expire_after_sixty_seconds(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $this->getJson('/api/products')->assertOk()->assertJsonPath('data.0.stock', 5);

        DB::enableQueryLog();
        $this->getJson('/api/products')->assertOk()->assertJsonPath('data.0.stock', 5);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(0, array_filter($queries, fn ($query) => str_contains($query['query'], '"products"')));

        // An out-of-band write bypasses API invalidation and demonstrates the TTL.
        DB::table('products')->where('id', $product->id)->update(['stock' => 9]);
        $this->getJson('/api/products')->assertJsonPath('data.0.stock', 5);
        $this->travel(61)->seconds();
        $this->getJson('/api/products')->assertOk()->assertJsonPath('data.0.stock', 9);
    }

    public function test_filters_and_pages_are_isolated_and_links_use_the_current_request(): void
    {
        $first = Product::factory()->create(['stock' => 0]);
        $second = Product::factory()->create(['stock' => 5]);
        $this->getJson('/api/products?per_page=1&page=1')->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/products?per_page=1&page=2')->assertJsonPath('data.0.id', $second->id);
        $this->getJson('/api/products?stock=0')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/products?stock=5')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);
        $this->getJson('/api/products?per_page=2')->assertJsonCount(2, 'data');

        $response = $this->getJson('http://inventory.test/api/products?page=1&per_page=1&extra=current')
            ->assertOk()->assertJsonPath('data.0.id', $first->id);
        $next = $response->json('links.next');
        $this->assertStringStartsWith('http://inventory.test/api/products?', $next);
        $this->assertStringContainsString('extra=current', $next);
        $this->assertStringContainsString('page=2', $next);
    }

    public function test_successful_writes_invalidate_all_list_variants_including_supplier_only_changes(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $this->getJson('/api/products')->assertJsonCount(1, 'data');
        $this->getJson('/api/products?stock=0')->assertJsonCount(0, 'data');
        $id = $this->postJson('/api/products', [
            'category_id' => $product->category_id, 'sku' => 'CACHE-NEW',
            'name' => 'Cached product', 'price' => '10.00', 'stock' => 0,
        ])->assertCreated()->json('data.id');
        $this->getJson('/api/products')->assertJsonCount(2, 'data');
        $this->getJson('/api/products?stock=0')->assertJsonPath('data.0.id', $id);

        $this->patchJson("/api/products/{$id}", ['stock' => 8])->assertOk();
        $this->getJson('/api/products?stock=0')->assertJsonCount(0, 'data');
        $this->getJson('/api/products')->assertJsonPath('data.1.stock', 8);

        $supplier = Supplier::factory()->create();
        $this->patchJson("/api/products/{$id}", ['supplier_ids' => [$supplier->id]])->assertOk();
        $this->getJson('/api/products')->assertJsonPath('data.1.suppliers.0.id', $supplier->id);
        $this->patchJson("/api/products/{$id}", ['supplier_ids' => []])->assertOk();
        $this->getJson('/api/products')->assertJsonCount(0, 'data.1.suppliers');

        $this->deleteJson("/api/products/{$id}")->assertNoContent();
        $this->getJson('/api/products')->assertJsonCount(1, 'data');
    }

    public function test_cached_lists_still_require_authentication_and_valid_filters(): void
    {
        Product::factory()->create();
        $this->getJson('/api/products')->assertOk();
        $this->getJson('/api/products?page=0')->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_database_cache_store_preserves_relationships_and_supports_invalidation(): void
    {
        config(['cache.default' => 'database']);
        $product = Product::factory()->hasAttached(Supplier::factory())->create(['stock' => 5]);
        $this->getJson('/api/products')->assertOk()->assertJsonCount(1, 'data.0.suppliers');
        DB::enableQueryLog();
        $this->getJson('/api/products')->assertOk()
            ->assertJsonPath('data.0.category.id', $product->category_id)
            ->assertJsonCount(1, 'data.0.suppliers');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(0, array_filter($queries, fn ($query) => str_contains($query['query'], '"products"')));
        $this->patchJson("/api/products/{$product->id}", ['stock' => 0])->assertOk();
        $this->getJson('/api/products')->assertOk()->assertJsonPath('data.0.in_stock', false);
    }
}
