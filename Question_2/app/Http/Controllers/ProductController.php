<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexProductRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(IndexProductRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return ProductResource::collection(
            Product::with(['category', 'suppliers'])->filter($filters)->orderBy('id')
                ->paginate($filters['per_page'] ?? 15)->withQueryString()
        );
    }

    public function store(ProductRequest $request): ProductResource
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->safe()->except('supplier_ids'));
            $product->suppliers()->sync($request->validated('supplier_ids', []));

            return $product;
        });

        return new ProductResource($product->load(['category', 'suppliers']));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'suppliers']));
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        DB::transaction(function () use ($request, $product) {
            $product->update($request->safe()->except('supplier_ids'));

            // Omission preserves suppliers; an explicit empty array detaches all.
            if ($request->has('supplier_ids')) {
                $product->suppliers()->sync($request->validated('supplier_ids'));
            }
        });

        return new ProductResource($product->load(['category', 'suppliers']));
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }
}
