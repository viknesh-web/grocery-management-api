# Backend Setup Instructions

## Critical Setup Steps

### 1. Create Storage Link
Run this command to create a symbolic link for image storage:

```bash
php artisan storage:link
```

This creates a link from `public/storage` to `storage/app/public`, allowing images to be accessible via the web.

### 2. Set Storage Permissions
```bash
chmod -R 775 storage
chmod -R 775 public/storage
```

### 3. Verify Image Paths
Images are stored in:
- Products: `storage/app/public/products/`
- Categories: `storage/app/public/categories/`
- PDFs: `storage/app/public/pdfs/`

The `ImageService` handles uploads and the models have `image_url` accessors that generate the correct URLs.

## Fixed Issues

### ✅ FormData Parsing
- All Form Requests now use `array_key_exists()` instead of `$this->has()` for better FormData compatibility
- `prepareForValidation()` methods read directly from `$this->all()` to properly parse multipart/form-data

### ✅ Image Display
- Models use `Storage::disk('public')->url()` as fallback if storage link doesn't exist
- Image URLs are properly generated in resource classes

### ✅ CRUD Operations
- All controllers remove `_method` field if present
- Controllers use `->fresh()` to reload relationships after update
- Validation rules properly handle FormData on PUT/PATCH requests

### ✅ Error Messages
- Frontend displays validation errors in user-friendly format
- Error messages are formatted with bullet points for readability

### ✅ PDF Generation
- Route exists at `/api/v1/whatsapp/generate-price-list`
- Frontend properly calls the API and opens PDF in new window

## Testing Checklist

- [ ] Run `php artisan storage:link`
- [ ] Test product create with image
- [ ] Test product update with image
- [ ] Test category create with image
- [ ] Test category update with image
- [ ] Test customer create
- [ ] Test customer update
- [ ] Test PDF generation
- [ ] Test WhatsApp message sending
- [ ] Verify images display correctly
- [ ] Check all validation error messages





