<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\SupplierResource;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReferenceController extends Controller
{
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::orderBy('id')->paginate(50));
    }

    public function suppliers(): AnonymousResourceCollection
    {
        return SupplierResource::collection(Supplier::orderBy('id')->paginate(50));
    }
}
