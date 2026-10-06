<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class SwaggerController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $spec = json_decode(file_get_contents(base_path('openapi.json')), true, 512, JSON_THROW_ON_ERROR);
        $spec['servers'] = [['url' => url('/api'), 'description' => 'This Laravel server']];

        return response()->json($spec);
    }
}
