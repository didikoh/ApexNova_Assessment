<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // PATCH accepts partial changes; PUT requires the complete required fields.
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'category_id' => [$presence, 'required', 'integer', 'exists:categories,id'],
            'sku' => [$presence, 'required', 'string', 'max:64', 'regex:/\A[A-Z0-9_-]+\z/',
                Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'name' => [$presence, 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'price' => [$presence, 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'stock' => [$presence, 'required', 'integer', 'min:0', 'max:2147483647'],
            'supplier_ids' => ['sometimes', 'array', 'max:100'],
            'supplier_ids.*' => ['required', 'integer', 'distinct', 'exists:suppliers,id'],
        ];
    }
}
