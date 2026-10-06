<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductListCache
{
    private const VERSION_KEY = 'products:list:version';

    public function paginate(array $filters): LengthAwarePaginator
    {
        $filters['page'] = (int) ($filters['page'] ?? 1);
        $filters['per_page'] = (int) ($filters['per_page'] ?? 15);
        ksort($filters);

        $version = Cache::get(self::VERSION_KEY, 'initial');
        $key = 'products:list:'.$version.':'.hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));

        $data = Cache::remember($key, 60, function () use ($filters) {
            $paginator = Product::with(['category', 'suppliers'])->filter($filters)->orderBy('id')
                ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);

            return ['items' => $paginator->getCollection(), 'total' => $paginator->total()];
        });

        // Build links for this request, rather than caching another request's URL.
        return new LengthAwarePaginator(
            $data['items'], $data['total'], $filters['per_page'], $filters['page'],
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    public function invalidate(): void
    {
        // Switch every filter/page to a new namespace; old entries expire in 60 seconds.
        // A concurrent reader can only populate its old namespace, not the new one.
        Cache::forever(self::VERSION_KEY, (string) Str::uuid());
    }
}
