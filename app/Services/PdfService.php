<?php

namespace App\Services;

use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    // public function generatePriceList(array $productIds = []): string
    // {
    //     $query = Product::enabled()->with('category');

    //     if (!empty($productIds)) {
    //         $query->whereIn('id', $productIds);
    //     }

    //     $products = $query->orderBy('category_id')->orderBy('name')->get();

    //     $groupedProducts = $products->groupBy('category.name');

    //     $data = [
    //         'groupedProducts' => $groupedProducts,
    //         'date' => now()->format('d M Y'),
    //         'time' => now()->format('h:i A'),
    //     ];

    //     $pdf = Pdf::loadView('pdfs.price-list', $data);
    //     $pdf->setPaper('A4', 'portrait');

    //     $filename = 'pdfs/price-list-' . date('Y-m-d-H-i-s') . '-' . uniqid() . '.pdf';
    //     $path = storage_path('app/public/' . $filename);

    //     $directory = dirname($path);
    //     if (!is_dir($directory)) {
    //         mkdir($directory, 0755, true);
    //     }

    //     $pdf->save($path);

    //     return $filename;
    // }

    public function generatePriceList(array $productIds = []): string
    {
        $query = Product::enabled()->with('category');

        if (!empty($productIds)) {
            $query->whereIn('id', $productIds);
        }

        $products = $query
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        $groupedProducts = $products->groupBy('category.name');

        $data = [
            'groupedProducts' => $groupedProducts,
            'date' => now()->format('d M Y'),
            'time' => now()->format('h:i A'),
        ];

        $pdf = Pdf::loadView('pdfs.price-list', $data);
        $pdf->setPaper('A4', 'portrait');

        $disk = Storage::disk('media');

        $filename = 'price-list-' . now()->format('Y-m-d-H-i-s') . '.pdf';
        $path = 'pdfs/' . $filename;

        $directory = $disk->path('pdfs');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Store PDF
        $disk->put($path, $pdf->output(), 'public');

        // Final verification
        if (!$disk->exists($path)) {
            throw new \RuntimeException('Failed to store price list PDF');
        }

        return $path; // return relative media path (pdfs/xxx.pdf)
    }

    public function getPdfUrl(string $pdfPath): string
    {
        return Storage::disk('media')->url($pdfPath);
    }

    public function getPdfPath(string $pdfPath): string
    {
        return Storage::disk('media')->path($pdfPath);
    }

    public function cleanupOldPdfs(int $days = 7): int
    {
        $deleted = 0;
        $files = Storage::disk('public')->files('pdfs');
        $cutoffTime = now()->subDays($days)->timestamp;

        foreach ($files as $file) {
            $lastModified = Storage::disk('public')->lastModified($file);
            if ($lastModified < $cutoffTime) {
                Storage::disk('public')->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}

