<?php

namespace App\Http\Controllers\API\V1;
    
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\OrderPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderFormController extends Controller
{
    public function show(Request $request)
    {
        $products = Product::with('category')
            ->where('enabled', true)
            ->get();
            Log::debug('$products');
            Log::debug($products);
        $categories = Category::orderBy('name')->get();
        $selectedQty = session('review_qty', []);
        if ($request->get('from') !== 'review') {
            session()->forget(['review_products', 'review_qty']);
            $selectedQty = [];
        }
        return view('order.form', compact('products', 'categories', 'selectedQty'));
    }

    public function showReview()
    {
        $products = session('review_products');

        if (!$products || $products->isEmpty()) {
            return redirect()->route('order.form')
                ->with('error', 'No products in review. Please select products first.');
        }

        return view('order.review', compact('products'));
    }

    public function downloadPdf(Request $request, OrderPdfService $pdfService)
    {
        return $pdfService->generate($request->products);
    }

    public function review(Request $request)
    {
        $productsInput = collect($request->products)
            ->filter(fn($p) => isset($p['qty']) && $p['qty'] > 0);

        if ($productsInput->isEmpty()) {
            return redirect()->back()->with('error', 'Select at least one product');
        }
        $productIds = $productsInput->keys();
        $products = Product::whereIn('id', $productIds)->get();
        $products->each(function ($product) use ($productsInput) {
            $product->qty = $productsInput[$product->id]['qty'];
        });
        session([
            'review_products' => $products,
            'review_qty' => $productsInput->toArray()
        ]);
        return view('order.review', compact('products'));
    }

    public function confirm(Request $request)
    {
        $data = $request->validate([
            'customer_name' => 'required',
            'whatsapp' => 'required',
            'email' => 'nullable|email',
            'address' => 'required',
            'grand_total' => 'required'
        ]);

        return view('order.confirmation', compact('data'));
    }
}
