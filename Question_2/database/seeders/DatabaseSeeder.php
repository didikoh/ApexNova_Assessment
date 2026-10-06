<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Demo credentials and fixtures are only appropriate for local evaluation.
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Inventory Admin',
            'password' => 'password',
        ]);

        $electronics = Category::firstOrCreate(['name' => 'Electronics']);
        $office = Category::firstOrCreate(['name' => 'Office']);
        $supplier = Supplier::firstOrCreate(['email' => 'sales@example.com'], ['name' => 'Apex Supplies']);
        $backup = Supplier::firstOrCreate(['email' => 'orders@example.com'], ['name' => 'Nova Wholesale']);

        foreach ([
            ['sku' => 'KB-001', 'name' => 'Keyboard', 'price' => '79.90', 'stock' => 25, 'category_id' => $electronics->id],
            ['sku' => 'MS-001', 'name' => 'Mouse', 'price' => '29.90', 'stock' => 0, 'category_id' => $electronics->id],
            ['sku' => 'NB-001', 'name' => 'Notebook', 'price' => '5.50', 'stock' => 100, 'category_id' => $office->id],
        ] as $attributes) {
            $product = Product::withTrashed()->firstOrCreate(['sku' => $attributes['sku']], $attributes);
            $product->suppliers()->syncWithoutDetaching([$supplier->id, $backup->id]);
        }
    }
}
