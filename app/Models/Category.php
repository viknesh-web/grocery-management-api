<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    public static $STORAGE_PATH_CATEGORY = 'media/';
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_active',
        'display_order',
        'parent_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    protected $appends = [
        'image_url',
        'products_count',
        'is_parent',
        'full_path',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Get the user who created the category.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the category.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get child categories (subcategories).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('display_order');
    }

    /**
     * Get all products in this category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get active products in this category.
     */
    public function activeProducts(): HasMany
    {
        return $this->hasMany(Product::class)->where('enabled', true);
    }

    /**
     * Get the image URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        $imagePath = ltrim($this->image, '/');

        if ($imagePath && Storage::disk('media')->exists($imagePath)) {
            return Storage::disk('media')->url($imagePath);
        }

        return asset('assets/images/no-image.png');

    }

    /**
     * Get products count.
     */
    public function getProductsCountAttribute(): int
    {
        return $this->products_count ?? $this->products()->count();
    }

    /**
     * Check if category has children.
     */
    public function getIsParentAttribute(): bool
    {
        return $this->children()->count() > 0;
    }

    /**
     * Get full breadcrumb path for nested categories.
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Scope a query to only include active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include root categories (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope a query to search categories by name.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('name', 'like', "%{$search}%")
            ->orWhere('slug', 'like', "%{$search}%");
    }

    /**
     * Scope a query to order by display_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
