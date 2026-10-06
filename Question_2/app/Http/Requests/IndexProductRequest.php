<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'min_price' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'max_price' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99',
                ...($this->has('min_price') ? ['gte:min_price'] : [])],
            'stock' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'min_stock' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'max_stock' => ['sometimes', 'integer', 'min:0', 'max:2147483647',
                ...($this->has('min_stock') ? ['gte:min_stock'] : [])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
