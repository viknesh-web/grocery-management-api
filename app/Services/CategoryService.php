<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Category Service
 * 
 * Handles all business logic for category operations.
 */
class CategoryService
{
    public function __construct(
        private CategoryRepository $repository,
        private ImageService $imageService
    ) {}

    /**
     * Get paginated categories with filters.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaginated(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $relations = [];
        return $this->repository->paginate($filters, $perPage, $relations);
    }

    /**
     * Get a category by ID.
     *
     * @param int $id
     * @return Category|null
     */
    public function find(int $id): ?Category
    {
        return Category::find($id);
    }

    /**
     * Create a new category.
     *
     * @param array $data
     * @param UploadedFile|null $image
     * @param int $userId
     * @return Category
     */
    public function create(array $data, ?UploadedFile $image, int $userId): Category
    {
        DB::beginTransaction();
        try {
            // Handle image upload
            if ($image) {
                $data['image'] = $this->imageService->uploadCategoryImage($image);
            }

            $category = Category::create($data);

            DB::commit();

            // Clear category cache after creation
            \App\Services\CacheService::clearCategoryCache();

            return $category;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update a category.
     *
     * @param Category $category
     * @param array $data
     * @param UploadedFile|null $image
     * @param bool $imageRemoved
     * @param int $userId
     * @return Category
     */
    public function update(Category $category, array $data, ?UploadedFile $image, bool $imageRemoved, int $userId): Category
    {
        DB::beginTransaction();
        try {
            // Handle image upload
            if ($image) {
                // Delete old image if exists
                if ($category->image) {
                    $this->imageService->deleteCategoryImage($category->image);
                }
                $data['image'] = $this->imageService->uploadCategoryImage($image, $category->image);
            } elseif ($imageRemoved) {
                if ($category->image) {
                    $this->imageService->deleteCategoryImage($category->image);
                }
                $data['image'] = null;
            }

            $category->update($data);
            $category->refresh();

            DB::commit();

            // Clear specific category and list caches
            \App\Services\CacheService::clearCategory($category->id);

            return $category;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a category.
     *
     * @param Category $category
     * @return bool
     * @throws \Exception
     */
    public function delete(Category $category): bool
    {
        // Check if category has products
        if ($this->repository->hasProducts($category)) {
            $productCount = $category->products()->count();
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

        $categoryId = $category->id;

        DB::beginTransaction();
        try {
            // Delete image if exists
            if ($category->image) {
                $this->imageService->deleteCategoryImage($category->image);
            }

            $result = $category->delete();

            DB::commit();

            // Clear category cache
            \App\Services\CacheService::clearCategory($categoryId);

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Toggle category active status.
     *
     * @param Category $category
     * @param int $userId
     * @return Category
     */
    public function toggleStatus(Category $category, int $userId): Category
    {
        $newStatus = $category->status === 'active' ? 'inactive' : 'active';
        $category->update([
            'status' => $newStatus,
        ]);

        return $category->fresh();
    }

    /**
     * Reorder categories.
     *
     * @param array $categories Array of ['id' => int] (display_order no longer supported)
     * @return void
     */
    public function reorder(array $categories): void
    {
        $this->repository->reorder($categories);
    }

    /**
     * Search categories.
     *
     * @param string $query
     * @return Collection
     */
    public function search(string $query): Collection
    {
        $relations = [];
        return $this->repository->search($query, $relations);
    }

    /**
     * Get category tree (hierarchical structure).
     * Note: Hierarchical structure is no longer supported, returns flat list of active categories.
     *
     * @return Collection
     */
    public function getTree(): Collection
    {
        return $this->repository->getRootCategories();
    }

    /**
     * Get products in a category.
     *
     * @param Category $category
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getProducts(Category $category, array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = $category->products()->with(['category:id,name']);

        // Search
        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Sort
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }
}



