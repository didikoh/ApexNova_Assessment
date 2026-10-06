<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexProductRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductListCache;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(private readonly ProductListCache $productListCache) {}

    public function index(IndexProductRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return ProductResource::collection(
            $this->productListCache->paginate($filters)->withQueryString()
        );
    }

    public function store(ProductRequest $request): ProductResource
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->safe()->except('supplier_ids'));
            $product->suppliers()->sync($request->validated('supplier_ids', []));

            return $product;
        });

        $this->productListCache->invalidate();

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

        $this->productListCache->invalidate();

        return new ProductResource($product->load(['category', 'suppliers']));
    }

    public function destroy(Product $product): Response
    {
        $product->delete();
        $this->productListCache->invalidate();

        return response()->noContent();
    }
}
