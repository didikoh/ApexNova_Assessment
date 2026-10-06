Question 2: Brief: Build a REST API for a Product Inventory Management System using
Laravel 11.x.
Requirements:
- Models: Product, Category, Supplier with proper relationships (Product
belongsTo Category, Product belongsToMany Supplier)
- Full CRUD endpoints for Products with filtering (by category, price range, stock
level) and pagination
- Authentication using Laravel Sanctum
- Use Form Request classes for validation
- Use API Resources for response formatting
- Include at least one Eloquent scope and one accessor/mutator
- Implement soft deletes on Products
- Write Feature tests for the main endpoints (minimum 5 tests)
- Include migrations, seeders, and a README with setup instructions
- Submit via GitHub repository
Bonus points: Docker setup, API documentation (Swagger/OpenAPI), caching layer,
rate limiting.