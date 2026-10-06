# Question 2: Product Inventory API

Laravel 11 REST API with Sanctum bearer authentication, product CRUD, category and supplier relationships, filtering, pagination, and soft deletes. All work for Question 2 is contained in this directory.

## Local setup

Requires PHP 8.2+ with Laravel's extensions, PDO SQLite, and Composer 2.9.2+. SQLite is the default, so no separate database server or frontend build is needed.

The project was started with:

```sh
composer create-project laravel/laravel Question_2 "11.*"
```

From the repository root, run:

```sh
cd Question_2
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

API base URL: `http://127.0.0.1:8000/api`. Health check: `http://127.0.0.1:8000/up`.

The local/testing seeder creates an account (`admin@example.com` / `password`), two categories, two suppliers, and three products. It can be rerun without duplicating records, resetting the account password, or restoring deleted products. It does not create demo data outside the `local` and `testing` environments.

## Authentication and usage

All inventory endpoints require `Authorization: Bearer <token>`. Login issues a token valid for 24 hours; logout revokes only the current token. There is no public registration endpoint. This is a shared inventory: all provisioned users have equal CRUD access, rather than separate inventories or administrator roles. Sanctum is configured for bearer tokens only.

PowerShell example:

```powershell
$baseUrl = 'http://127.0.0.1:8000/api'
$login = Invoke-RestMethod -Method Post -Uri "$baseUrl/login" -ContentType 'application/json' -Body '{"email":"admin@example.com","password":"password","device_name":"assessment"}'
$headers = @{ Authorization = "Bearer $($login.data.token)"; Accept = 'application/json' }
Invoke-RestMethod -Uri "$baseUrl/categories" -Headers $headers
Invoke-RestMethod -Uri "$baseUrl/suppliers" -Headers $headers
Invoke-RestMethod -Uri "$baseUrl/products?min_price=5&max_price=100&min_stock=1&per_page=10" -Headers $headers

# Use category and supplier IDs returned by the reference endpoints.
$payload = @{ sku = 'KB-002'; name = 'Wireless keyboard'; category_id = 1; price = '49.90'; stock = 12; supplier_ids = @(1, 2) } | ConvertTo-Json
$created = Invoke-RestMethod -Method Post -Uri "$baseUrl/products" -Headers $headers -ContentType 'application/json' -Body $payload
$productId = $created.data.id
Invoke-RestMethod -Method Patch -Uri "$baseUrl/products/$productId" -Headers $headers -ContentType 'application/json' -Body '{"stock":0}'
Invoke-RestMethod -Method Delete -Uri "$baseUrl/products/$productId" -Headers $headers
Invoke-RestMethod -Method Post -Uri "$baseUrl/logout" -Headers $headers
```

## API contract

The complete [OpenAPI 3.0 specification](openapi.json) can be imported into Swagger Editor or Postman.

### Swagger UI

With Laravel running, open **http://localhost:8000/swagger** (or **http://127.0.0.1:8000/swagger**). Laravel serves the UI and local Swagger UI 5.33.1 assets directly; no frontend or Node.js server is required. The specification is served at `/swagger/openapi.json` using the existing `openapi.json`, with the API server URL set to the current Laravel origin plus `/api`.

Execute `POST /login`, copy `data.token`, then select **Authorize** and paste the token to call protected endpoints. Documentation is public; inventory operations still require authentication and affect the configured database. Tokens are not persisted across page reloads, and external Swagger validation is disabled. Asset source and license notices are included in `public/swagger-assets/`.

### Endpoints

| Method | Endpoint | Result |
| --- | --- | --- |
| POST | `/api/login` | `200`: token, token type, expiry |
| POST | `/api/logout` | `204`: current token revoked |
| GET | `/api/products` | `200`: filtered, paginated products |
| POST | `/api/products` | `201`: created product |
| GET | `/api/products/{id}` | `200`: product with category and suppliers |
| PUT | `/api/products/{id}` | `200`: update required fields |
| PATCH | `/api/products/{id}` | `200`: partial update |
| DELETE | `/api/products/{id}` | `204`: soft delete |
| GET | `/api/categories` | `200`: reference data, 50 per page |
| GET | `/api/suppliers` | `200`: reference data, 50 per page |

Single responses use a `data` wrapper; paginated responses also include `links` and `meta`. Prices are decimal strings in responses, for example `"49.90"`. Errors return JSON, including when the client omits its `Accept` header: `401` for invalid authentication, `404` for missing/deleted products, `422` for validation errors or incorrect login credentials, and `429` for rate limiting.

### Product fields

POST and PUT require `category_id`, `sku`, `name`, `price`, and `stock`. PATCH validates only supplied fields. Optional fields omitted on update are preserved.

- `category_id`: an existing category ID.
- `sku`: globally unique, maximum 64 characters; uppercase letters, digits, underscores, and hyphens. Deleted products retain their SKU reservation.
- `name`: non-empty string, maximum 255 characters.
- `description`: optional, nullable text, maximum 10,000 characters. Send `null` to clear it.
- `price`: non-negative amount, at most two decimal places, maximum `9999999999.99`. JSON numbers and decimal strings are accepted.
- `stock`: non-negative integer, maximum `2147483647`.
- `supplier_ids`: optional array of at most 100 distinct, existing supplier IDs. On update, omission keeps existing suppliers; `[]` detaches all; a supplied list replaces them.

Product writes and supplier synchronization run in a database transaction. Category foreign keys are enforced, and the supplier pivot has a composite primary key. Soft deletes preserve the row and supplier links; deleted products are excluded from lists and route binding. No restore or permanent-delete endpoint is provided.

### Filters and pagination

`GET /api/products` accepts these query parameters, combined with AND:

| Parameter | Meaning |
| --- | --- |
| `category_id` | Existing category ID |
| `min_price`, `max_price` | Inclusive price bounds |
| `stock` | Exact stock count, including zero |
| `min_stock`, `max_stock` | Inclusive stock bounds |
| `page` | Positive page number; default 1 |
| `per_page` | 1-100 records; default 15 |

Upper bounds must not be below supplied lower bounds. Results use ascending product ID for stable ordering, retain filters in pagination links, and eagerly load relationships to avoid N+1 queries. `Product::scopeFilter()` supplies the Eloquent scope; the `in_stock` accessor derives a boolean from `stock > 0`.

### Product list caching

`App\Services\ProductListCache` caches `GET /api/products` query results, including loaded categories, suppliers, and pagination totals, for 60 seconds using Laravel's configured cache store (`CACHE_STORE=database` by default). Keys include sorted validated filters, page, and page size. Pagination URLs are rebuilt for each request. Authentication, validation, and rate limits still run on cache hits; the shared inventory allows authenticated users to reuse the same cached data.

Successful API creates, updates (including supplier-only changes), and soft deletes invalidate all list variants by rotating a cache version. Create/update invalidation happens after the product-and-supplier transaction completes. Old entries expire naturally, without flushing unrelated caches or rate-limit counters. Product detail and reference endpoints remain uncached. Writes outside these API endpoints (such as direct SQL, seeders, or future category/supplier edits) can remain stale in lists for up to 60 seconds; those writers can call `ProductListCache::invalidate()` after committing if immediate freshness is required.

### Rate limits

Login is limited to 5 requests per minute per IP, including failed attempts. Authenticated routes share a limit of 60 requests per minute per user across that user's tokens. Responses exceeding the limit include `Retry-After`. The default database cache store persists limiter state across requests.

## Verification

```sh
composer test
php vendor/bin/pint --test
composer validate --strict
php artisan route:list --path=api
composer audit
```

Tests use an isolated in-memory SQLite database, never the local demo database. The suite covers token issuance, expiry and revocation, unauthenticated access, validation, rate limits, CRUD, supplier replacement/preservation, combined filters, zero values, pagination, soft deletion, SKU reservation, and repeatable seeding.

Local verification: PHP 8.2.12, Laravel 11.57.0, Sanctum 4.3.3. After adding product list caching and Laravel-hosted Swagger, `php artisan test` passed all 31 tests (269 assertions), including 29 inventory/authentication/cache/Swagger feature tests and the two original Laravel smoke tests; `php vendor/bin/pint --test` also passed. Cache tests cover query reuse, expiry, filter/page isolation, current-request pagination links, write invalidation, authentication, validation, and the database cache store. Swagger tests cover public documentation, local assets, the current-origin API URL, and preservation of the API contract. Live HTTP checks returned 200 for `/swagger`, `/swagger/openapi.json`, and both CSS/JavaScript assets at `127.0.0.1:8000`. Browser interaction was not tested. Migrations and seeders completed successfully during the original setup. See the security note below for the previously recorded audit failure.

## Optional Docker development environment

With Docker Desktop's Linux engine running:

```sh
docker compose up --build
```

The API binds to `127.0.0.1:8000`. Startup creates the local environment and app key, migrates the SQLite database, and seeds demo data. A named volume preserves the database. This uses PHP's development server and is intended for local assessment only. `docker compose down` preserves the data; adding `-v` deletes it.

Docker build and runtime verification passed on 2026-10-06 using Docker Desktop 4.65.0 (Linux engine). Because host port 8000 was occupied, verification used a temporary Compose override mapping `127.0.0.1:8001` to container port 8000; the default configuration remains unchanged. Checks passed for health (`200`), unauthenticated product access (`401`), login, authenticated product/category/supplier lists (3/2/2 seeded records), and logout (`204`). Database migrations also remained intact after container recreation. The startup command uses `--no-reload` so Laravel's development server preserves Docker environment variables, including the SQLite database path.

## Laravel 11 security note

The assessment explicitly requires Laravel 11.x. Composer currently reports four advisory records affecting the installed framework. Project-local `config.audit.ignore` entries use `apply: block` solely to allow that required version to resolve; **audit reporting remains enabled**. `composer audit` is expected to return a non-zero status. No global Composer settings were changed.

| Advisory | Issue |
| --- | --- |
| `PKSA-d5tc-s1qs-h781` | [Debug page XSS](https://github.com/advisories/GHSA-jh5r-qr3c-85q8) |
| `PKSA-m5cs-t1y6-qpcs` | [Temporary signed URL path confusion](https://github.com/advisories/GHSA-crmm-hgp2-wgrp) |
| `PKSA-3r5d-mb8f-1qw9`, `PKSA-mdq4-51ck-6kdq` | [Email validation CRLF injection](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq) |

Debug mode defaults to false, login uses strict email validation with an explicit CR/LF check, and the API does not use signed URLs. These measures do not constitute a framework patch. Upgrade to a supported, patched Laravel release and remove the exceptions before any production deployment.

Implementation references: [Laravel 11 Sanctum](https://laravel.com/docs/11.x/sanctum), [API Resources](https://laravel.com/docs/11.x/eloquent-resources), [validation](https://laravel.com/docs/11.x/validation), and [Composer audit configuration](https://getcomposer.org/doc/06-config.md#audit).

## Repository submission

Commit this directory, including `composer.lock`, migrations, tests, `openapi.json`, local Swagger assets and their licenses, and this README. Its `.gitignore` excludes `.env`, dependencies, generated databases, and runtime files. Keep unrelated assessment and personal documents outside the submitted repository. The configured GitHub remote is `https://github.com/didikoh/ApexNova_Assessment.git`. The repository-level `.gitignore` excludes `docs/` and the frontend; the standalone Laravel application includes its own setup guide and Swagger UI.
