# Clean Architecture Refactoring Guide

## Overview

This document outlines the clean architecture refactoring pattern applied to the Laravel project. The refactoring separates concerns into three main layers:

1. **Controllers** - Handle HTTP requests/responses only
2. **Services** - Contain business logic
3. **Repositories** - Handle data access

## Completed: Category Resource

The Category resource has been fully refactored as a reference implementation.

### Files Created/Modified

#### Repository Layer
- ✅ `app/Repositories/Contracts/CategoryRepositoryInterface.php`
- ✅ `app/Repositories/CategoryRepository.php`

#### Service Layer
- ✅ `app/Services/CategoryService.php`

#### Form Requests
- ✅ `app/Http/Requests/Category/StoreCategoryRequest.php`
- ✅ `app/Http/Requests/Category/UpdateCategoryRequest.php`

#### Controller
- ✅ `app/Http/Controllers/API/V1/CategoryController.php` (refactored to be thin)

#### Infrastructure
- ✅ `app/Providers/RepositoryServiceProvider.php`
- ✅ `bootstrap/providers.php` (registered RepositoryServiceProvider)

## Remaining Work

### Product Resource

#### Files to Create:
1. `app/Repositories/ProductRepository.php`
2. `app/Services/ProductService.php`
3. `app/Http/Requests/Product/StoreProductRequest.php`
4. `app/Http/Requests/Product/UpdateProductRequest.php`

#### Files to Refactor:
1. `app/Http/Controllers/API/V1/ProductController.php`

#### Key Points:
- Extract ProductFilterService usage to repository
- Move discount logic to service
- Handle image uploads in service
- Extract all Eloquent queries to repository

### Customer Resource

#### Files to Create:
1. `app/Repositories/CustomerRepository.php`
2. `app/Services/CustomerService.php`
3. `app/Http/Requests/Customer/StoreCustomerRequest.php`
4. `app/Http/Requests/Customer/UpdateCustomerRequest.php`

#### Files to Refactor:
1. `app/Http/Controllers/API/V1/CustomerController.php`

#### Key Points:
- Move WhatsApp number normalization to Form Request
- Extract all Eloquent queries to repository

### Price Update Resource

#### Files to Create:
1. `app/Repositories/PriceUpdateRepository.php`

#### Files to Refactor:
1. `app/Http/Controllers/API/V1/PriceUpdateController.php`

#### Key Points:
- PriceUpdateService already exists, may need adjustments
- Extract queries to repository

### Other Resources

- DashboardController - Create DashboardService
- WhatsAppController - Already uses WhatsAppService (may need minor adjustments)
- AddressController - Already uses AddressService (may need minor adjustments)

## Route Model Binding

Routes need to be updated to use route model binding instead of middleware injection.

### Current Pattern:
```php
Route::group(['prefix' => 'categories', 'middleware' => [CheckCategory::class]], function () {
    Route::get('/{category}', [CategoryController::class, 'show']);
});
```

### New Pattern:
```php
Route::group(['prefix' => 'categories'], function () {
    Route::get('/{category}', [CategoryController::class, 'show']);
});
```

Laravel will automatically resolve the `{category}` parameter using route model binding.

## Form Request Pattern

All normalization logic should move from `NormalizesInput` trait to Form Request's `prepareForValidation()` method.

### Example (StoreCategoryRequest):
```php
protected function prepareForValidation(): void
{
    $dataToMerge = [];
    
    // Normalize name
    if ($this->has('name') && $this->name !== null && $this->name !== '') {
        $dataToMerge['name'] = trim((string) $this->name);
        // Auto-generate slug
        if (!isset($this->slug) || empty($this->slug)) {
            $dataToMerge['slug'] = Str::slug($dataToMerge['name']);
        }
    }
    
    // ... more normalization
    
    if (!empty($dataToMerge)) {
        $this->merge($dataToMerge);
    }
}
```

## Service Pattern

Services should:
1. Accept validated data from Form Requests
2. Handle business logic
3. Use repositories for data access
4. Handle transactions
5. Coordinate between multiple services/repositories

### Example:
```php
public function create(array $data, ?UploadedFile $image, int $userId): Category
{
    DB::beginTransaction();
    try {
        if ($image) {
            $data['image'] = $this->imageService->uploadCategoryImage($image);
        }
        
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        
        $category = $this->repository->create($data);
        
        DB::commit();
        return $category;
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

## Repository Pattern

Repositories should:
1. Handle all database queries
2. Use model scopes
3. Return Eloquent models/collections
4. No business logic

### Example:
```php
public function paginate(array $filters = [], int $perPage = 15, array $relations = []): LengthAwarePaginator
{
    $query = Category::query();
    
    if (!empty($relations)) {
        $query->with($relations);
    }
    
    // Apply filters
    if (isset($filters['active'])) {
        $query->where('is_active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
    }
    
    // ... more filters
    
    return $query->paginate($perPage);
}
```

## Controller Pattern

Controllers should:
1. Be thin (20-30 lines per method)
2. Use Form Requests for validation
3. Delegate to services
4. Return JSON responses

### Example:
```php
public function store(StoreCategoryRequest $request): JsonResponse
{
    $category = $this->categoryService->create(
        $request->validated(),
        $request->file('image'),
        $request->user()->id
    );
    
    return response()->json([
        'message' => 'Category created successfully',
        'data' => new CategoryResource($category),
    ], 201);
}
```

## Files to Remove After Complete Refactoring

Once all resources are refactored:
1. `app/Http/Traits/NormalizesInput.php` - Logic moved to Form Requests
2. `app/Validator/ProductValidator.php` - Replaced by Form Requests
3. `app/Validator/CategoryValidator.php` - Replaced by Form Requests
4. `app/Validator/CustomerValidator.php` - Replaced by Form Requests
5. Middleware classes (CheckCategory, CheckProduct, CheckCustomer) - Replaced by route model binding

## Breaking Changes

### None Expected
- All API endpoints remain the same
- All response formats remain the same
- All validation rules remain the same
- All business logic preserved

### Route Updates Needed
- Update `routes/api.php` to remove middleware from route groups
- Laravel will automatically handle route model binding

## Testing Checklist

After refactoring each resource:
- [ ] All CRUD operations work
- [ ] Validation rules work correctly
- [ ] Image uploads work
- [ ] Business logic preserved
- [ ] No breaking changes to API contracts
- [ ] Error handling works correctly

## Next Steps

1. Complete Product resource refactoring
2. Complete Customer resource refactoring
3. Complete Price Update resource refactoring
4. Update routes to use route model binding
5. Remove obsolete files (Validators, NormalizesInput trait, middleware)
6. Run comprehensive tests



