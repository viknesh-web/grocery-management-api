<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    public static $STORAGE_PATH_CATEGORY = 'media/';
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'image',
        'status',
    ];

    protected $casts = [];

    protected $appends = [
        'image_url',
        'products_count',
    ];



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
        return $this->hasMany(Product::class)->where('status', 'active');
    }

    /**
     * Get the image URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return asset('assets/images/no-image.png');
        }

        // Normalize path (remove leading slash)
        $imagePath = ltrim($this->image, '/');

        // If path already contains "category/", use as-is
        if (str_starts_with($imagePath, 'category/')) {
            $storagePath = $imagePath;
        } else {
            $storagePath = 'category/' . $imagePath;
        }


        if (Storage::disk('media')->exists($storagePath)) {
            return Storage::disk('media')->url($storagePath);
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
     * Scope a query to only include active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to search categories by name.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('name', 'like', "%{$search}%");
    }

}
