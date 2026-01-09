<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Category Service - Updated with Cascade Status
 */
class CategoryService extends BaseService
{
    public function __construct(
        private CategoryRepository $repository,
        private ProductRepository $productRepository,
        private ImageService $imageService
    ) {}
  
    public function getPaginated(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $relations = [];
        
        $categories = $this->repository->paginate($filters, $perPage, $relations);
        $totalFiltered = $this->repository->countByFilters($filters);
        $filtersApplied = array_filter($filters, fn($value) => !empty($value));
        $categories->filters_applied = $filtersApplied;
        $categories->total_filtered = $totalFiltered;

        return $categories;
    }
 
    public function find(int $id): ?Category
    {
        return $this->handle(function () use ($id) {
            $relations = [];
            
            return $this->repository->find($id, $relations);
        }, "Failed to find category with ID: {$id}");
    }
 
    public function findOrFail(int $id): Category
    {
        return $this->handle(function () use ($id) {
            $relations = [];
            
            return $this->repository->findOrFail($id, $relations);
        }, "Failed to find category with ID: {$id}");
    }

    public function create(array $data, ?UploadedFile $image, int $userId): Category
    {
        return $this->transaction(function () use ($data, $image, $userId) {
            if ($image) {
                $data['image'] = $this->imageService->uploadCategoryImage($image);
            }

            $data = $this->prepareCategoryData($data, $userId);
            $category = $this->repository->create($data);

            return $category;
        }, 'Failed to create category');
    }
 
    public function update(Category $category, array $data, ?UploadedFile $image, bool $imageRemoved, int $userId): Category
    {
        return $this->transaction(function () use ($category, $data, $image, $imageRemoved, $userId) {            
            $oldValues = $this->getOldCategoryValues($category);
            $data = $this->handleImageUpdate($category, $data, $image, $imageRemoved);
            $data = $this->prepareCategoryData($data, $userId, $category);
            $category = $this->repository->update($category, $data);           

            return $category;
        }, 'Failed to update category');
    }

    public function delete(Category $category): bool
    {
        return $this->transaction(function () use ($category) {
            if ($this->repository->hasProducts($category)) {
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

            if ($category->image) {
                $this->imageService->deleteCategoryImage($category->image);
            }
            $result = $this->repository->delete($category);          

            return $result;
        }, 'Failed to delete category');
    }

    /**
     * Toggle category status and cascade to products if becoming inactive.
     *
     * @param Category $category
     * @param int $userId
     * @return Category
     */
    public function toggleStatus(Category $category, int $userId): Category
    {
        return $this->transaction(function () use ($category, $userId) {
            $oldStatus = $category->status;
            $newStatus = $oldStatus === 'active' ? 'inactive' : 'active';
            
            // Update category status
            $category = $this->repository->update($category, [
                'status' => $newStatus,
            ]);

            // If category is becoming INACTIVE, cascade to all products
            if ($newStatus === 'inactive') {
                $affectedProducts = $this->cascadeInactiveStatusToProducts($category->id);
                
                Log::info('Category status toggled to inactive with cascade', [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'products_affected' => $affectedProducts,
                    'user_id' => $userId,
                ]);
            } else {
                Log::info('Category status toggled to active', [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'user_id' => $userId,
                ]);
            }

            $this->clearModelCache($category);

            return $category;
        }, 'Failed to toggle category status');
    }

    /**
     * Cascade inactive status to all products in the category.
     *
     * @param int $categoryId
     * @return int Number of products affected
     */
    private function cascadeInactiveStatusToProducts(int $categoryId): int
    {
        // Get all active products in this category
        $activeProducts = $this->productRepository->all([
            'category_id' => $categoryId,
            'status' => 'active'
        ]);

        if ($activeProducts->isEmpty()) {
            return 0;
        }

        // Update all active products to inactive
        $affectedCount = DB::table('products')
            ->where('category_id', $categoryId)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);
      

        return $affectedCount;
    }

    public function reorder(array $categories): void
    {
        $this->repository->reorder($categories);
    }

    public function search(string $query): Collection
    {
        $relations = [];
        return $this->repository->search($query, $relations);
    }

    public function getTree(): Collection
    {
        $relations = [];
        return $this->repository->getRootCategories($relations);
    }

    public function getProducts(Category $category, array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $relations = ['category:id,name'];
        return $this->productRepository->getByCategory($category->id, $filters, $perPage, $relations);
    }

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
  
    protected function getOldCategoryValues(Category $category): array
    {
        return [
            'name' => $category->name,
            'description' => $category->description,
            'status' => $category->status,
            'image' => $category->image,
        ];
    }

    protected function clearModelCache(?Model $model = null, ?int $id = null): void
    {
        if ($model instanceof Category) {
            CacheService::clearCategory($model->id);
        } elseif ($id !== null) {
            CacheService::clearCategory($id);
        }
    }
   
    protected function clearAllModelCache(): void
    {
        CacheService::clearCategoryCache();
    }
}