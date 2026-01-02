<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Category Service
 * 
 * Handles all business logic for category operations.
 * 
 * Responsibilities:
 * - Business logic orchestration
 * - Transaction management
 * - Image handling (delegated to ImageService)
 * - Cache management (delegated to CacheService)
 * - Business rule validation (e.g., category deletion with products)
 * - Status toggle logic
 * - Search functionality
 * - Tree structure methods (currently returns flat list)
 */
class CategoryService extends BaseService
{
    public function __construct(
        private CategoryRepository $repository,
        private ProductRepository $productRepository,
        private ImageService $imageService
    ) {}

    /**
     * Get paginated categories with filters.
     * 
     * Handles:
     * - Category pagination (via repository)
     * - Filter metadata calculation (via repository)
     * - Relation eager loading
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaginated(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $relations = [];
        
        // Get paginated categories via repository
        $categories = $this->repository->paginate($filters, $perPage, $relations);

        // Get filter metadata using repository's count method
        // This ensures we use the same filter logic as the pagination
        $totalFiltered = $this->repository->countByFilters($filters);
        $filtersApplied = array_filter($filters, fn($value) => !empty($value));

        // Store metadata as dynamic properties for controller to access
        $categories->filters_applied = $filtersApplied;
        $categories->total_filtered = $totalFiltered;

        return $categories;
    }

    /**
     * Get a category by ID.
     * 
     * Handles:
     * - Category retrieval (via repository)
     * - Relation eager loading
     * - Error handling (returns null if not found)
     *
     * @param int $id
     * @return Category|null
     */
    public function find(int $id): ?Category
    {
        return $this->handle(function () use ($id) {
            $relations = [];
            
            return $this->repository->find($id, $relations);
        }, "Failed to find category with ID: {$id}");
    }

    /**
     * Get a category by ID or throw an exception.
     * 
     * Handles:
     * - Category retrieval (via repository)
     * - Relation eager loading
     * - Throws exception if not found
     *
     * @param int $id
     * @return Category
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int $id): Category
    {
        return $this->handle(function () use ($id) {
            $relations = [];
            
            return $this->repository->findOrFail($id, $relations);
        }, "Failed to find category with ID: {$id}");
    }

    /**
     * Create a new category.
     * 
     * Handles:
     * - Image upload (delegated to ImageService)
     * - Data preparation (user tracking)
     * - Category creation (via repository)
     * - Cache clearing
     *
     * @param array $data
     * @param UploadedFile|null $image
     * @param int $userId
     * @return Category
     * @throws \Exception
     */
    public function create(array $data, ?UploadedFile $image, int $userId): Category
    {
        return $this->transaction(function () use ($data, $image, $userId) {
            // Handle image upload (business logic - when to upload)
            if ($image) {
                $data['image'] = $this->imageService->uploadCategoryImage($image);
            }

            // Prepare data (user tracking)
            $data = $this->prepareCategoryData($data, $userId);

            // Create category via repository
            $category = $this->repository->create($data);

            // Post-creation actions (cache clearing)
            $this->afterCreate($category, $data);

            return $category;
        }, 'Failed to create category');
    }

    /**
     * Update a category.
     * 
     * Handles:
     * - Image upload/removal (delegated to ImageService)
     * - Data preparation (user tracking)
     * - Category update (via repository)
     * - Cache clearing
     *
     * @param Category $category
     * @param array $data
     * @param UploadedFile|null $image
     * @param bool $imageRemoved
     * @param int $userId
     * @return Category
     * @throws \Exception
     */
    public function update(Category $category, array $data, ?UploadedFile $image, bool $imageRemoved, int $userId): Category
    {
        return $this->transaction(function () use ($category, $data, $image, $imageRemoved, $userId) {
            // Store old values for comparison
            $oldValues = $this->getOldCategoryValues($category);

            // Handle image upload/removal (business logic)
            $data = $this->handleImageUpdate($category, $data, $image, $imageRemoved);

            // Prepare data (user tracking)
            $data = $this->prepareCategoryData($data, $userId, $category);

            // Update category via repository
            $category = $this->repository->update($category, $data);

            // Post-update actions (cache clearing)
            $this->afterUpdate($category, $data, $oldValues);

            return $category;
        }, 'Failed to update category');
    }

    /**
     * Delete a category.
     * 
     * Handles:
     * - Business rule validation (check for products)
     * - Image cleanup (delegated to ImageService)
     * - Category deletion (via repository)
     * - Cache clearing
     *
     * @param Category $category
     * @return bool
     * @throws ValidationException If category has products
     * @throws \Exception
     */
    public function delete(Category $category): bool
    {
        return $this->transaction(function () use ($category) {
            // Business rule validation: Check if category has products
            if ($this->repository->hasProducts($category)) {
                // Get product count via repository for consistency
                $productCount = $this->productRepository->countByFilters(['category_id' => $category->id]);
                throw new ValidationException(
                    message: 'Cannot delete category with existing products',
                    errors: [
                        'category_id' => [
                            "This category has {$productCount} products. " .
                            'Please reassign or delete products first.'
                        ]
                    ]
                );
            }

            // Delete image if exists (business logic - cleanup)
            if ($category->image) {
                $this->imageService->deleteCategoryImage($category->image);
            }

            // Delete category via repository
            $result = $this->repository->delete($category);

            // Post-deletion actions (cache clearing)
            $this->afterDelete($category);

            return $result;
        }, 'Failed to delete category');
    }

    /**
     * Toggle category active status.
     * 
     * Handles:
     * - Status calculation (business logic)
     * - Category update (via repository)
     * - User tracking
     * - Cache clearing
     *
     * @param Category $category
     * @param int $userId
     * @return Category
     * @throws \Exception
     */
    public function toggleStatus(Category $category, int $userId): Category
    {
        return $this->transaction(function () use ($category, $userId) {
            // Calculate new status (business logic)
            $newStatus = $category->status === 'active' ? 'inactive' : 'active';
            
            // Update category via repository
            $category = $this->repository->update($category, [
                'status' => $newStatus,
            ]);

            // Clear cache after status change
            $this->clearModelCache($category);

            return $category;
        }, 'Failed to toggle category status');
    }

    /**
     * Reorder categories.
     * 
     * Note: Display order is no longer supported in the schema.
     * This method is kept for API compatibility but does nothing.
     *
     * @param array $categories Array of ['id' => int] (display_order no longer supported)
     * @return void
     */
    public function reorder(array $categories): void
    {
        // Display order is no longer supported
        // This method is kept for API compatibility but does nothing
        $this->repository->reorder($categories);
    }

    /**
     * Search categories.
     * 
     * Handles:
     * - Category search (via repository)
     * - Relation eager loading
     *
     * @param string $query
     * @return Collection<int, Category>
     */
    public function search(string $query): Collection
    {
        $relations = [];
        return $this->repository->search($query, $relations);
    }

    /**
     * Get category tree (hierarchical structure).
     * 
     * Note: Hierarchical structure is not yet implemented.
     * Currently returns flat list of active categories (root categories).
     * 
     * Handles:
     * - Root categories retrieval (via repository)
     * - Relation eager loading
     *
     * @return Collection<int, Category>
     */
    public function getTree(): Collection
    {
        $relations = [];
        return $this->repository->getRootCategories($relations);
    }

    /**
     * Get products in a category.
     * 
     * Handles:
     * - Product pagination by category (via ProductRepository)
     * - Filtering and sorting
     * - Relation eager loading
     *
     * @param Category $category
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getProducts(Category $category, array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $relations = ['category:id,name'];
        
        // Use ProductRepository to get products by category
        return $this->productRepository->getByCategory($category->id, $filters, $perPage, $relations);
    }

    /**
     * Prepare category data for create/update.
     * 
     * Handles:
     * - User tracking (created_by, updated_by)
     *
     * @param array $data
     * @param int $userId
     * @param Category|null $category Existing category (for updates)
     * @return array
     */
    protected function prepareCategoryData(array $data, int $userId, ?Category $category = null): array
    {
        // Add user tracking
        if ($category === null) {
            // Creating new category
            // Note: Categories may not have created_by/updated_by fields
        } else {
            // Updating existing category
            // Note: Categories may not have updated_by field
        }

        return $data;
    }

    /**
     * Handle image update logic.
     * 
     * Determines when to upload new image, remove existing image, or keep current.
     *
     * @param Category $category
     * @param array $data
     * @param UploadedFile|null $image
     * @param bool $imageRemoved
     * @return array Modified data array
     */
    protected function handleImageUpdate(Category $category, array $data, ?UploadedFile $image, bool $imageRemoved): array
    {
        if ($image) {
            // Upload new image and delete old one
            if ($category->image) {
                $this->imageService->deleteCategoryImage($category->image);
            }
            $data['image'] = $this->imageService->uploadCategoryImage($image, $category->image);
        } elseif ($imageRemoved) {
            // Remove existing image
            if ($category->image) {
                $this->imageService->deleteCategoryImage($category->image);
            }
            $data['image'] = null;
        }

        return $data;
    }

    /**
     * Get old category values for comparison.
     *
     * @param Category $category
     * @return array
     */
    protected function getOldCategoryValues(Category $category): array
    {
        return [
            'name' => $category->name,
            'description' => $category->description,
            'status' => $category->status,
            'image' => $category->image,
        ];
    }

    /**
     * Clear cache for a category.
     * 
     * Override from BaseService to implement category-specific cache clearing.
     *
     * @param \Illuminate\Database\Eloquent\Model|null $model
     * @param int|null $id
     * @return void
     */
    protected function clearModelCache(?Model $model = null, ?int $id = null): void
    {
        if ($model instanceof Category) {
            CacheService::clearCategory($model->id);
        } elseif ($id !== null) {
            CacheService::clearCategory($id);
        }
    }

    /**
     * Clear all category caches.
     * 
     * Override from BaseService to implement category-specific cache clearing.
     *
     * @return void
     */
    protected function clearAllModelCache(): void
    {
        CacheService::clearCategoryCache();
    }

    /**
     * Perform actions after category creation.
     * 
     * Override from BaseService to handle post-creation logic.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $data
     * @return void
     */
    protected function afterCreate(Model $model, array $data): void
    {
        // Clear all category caches after creation
        $this->clearAllModelCache();
    }

    /**
     * Perform actions after category update.
     * 
     * Override from BaseService to handle post-update logic.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $data
     * @param array $oldData
     * @return void
     */
    protected function afterUpdate(Model $model, array $data, array $oldData): void
    {
        // Clear specific category cache after update
        if ($model instanceof Category) {
            $this->clearModelCache($model);
        }
    }

    /**
     * Perform actions after category deletion.
     * 
     * Override from BaseService to handle post-deletion logic.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    protected function afterDelete(Model $model): void
    {
        // Clear category cache after deletion
        if ($model instanceof Category) {
            $this->clearModelCache($model);
        }
    }
}
