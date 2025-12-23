<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Services\OrderPdfService;
use Illuminate\Support\Facades\Log;

class OrderFormController extends Controller
{
    public function show(Request $request)
    {
        // 🔥 Clear ONLY if not coming from review
        if ($request->get('from') !== 'review') {
            session()->forget(['review_products', 'review_qty']);
        }

        $products = Product::with('category')->get();

        $categories = Category::orderBy('name')->get();

        $selectedQty = session('review_qty', []);

        return view('order.form', compact('products', 'categories', 'selectedQty'));
    }



    public function downloadPdf(Request $request, OrderPdfService $pdfService)
    {
        return $pdfService->generate($request->products);
    }

    public function review(Request $request)
    {
        // 1️⃣ Get only qty > 0 from form
        $productsInput = collect($request->products)
            ->filter(fn($p) => isset($p['qty']) && $p['qty'] > 0);

        if ($productsInput->isEmpty()) {
            return redirect()->back()->with('error', 'Select at least one product');
        }

        // 2️⃣ Get product IDs
        $productIds = $productsInput->keys();

        // 3️⃣ Fetch products from DB
        $products = Product::whereIn('id', $productIds)->get();

        // 4️⃣ Attach qty to each product model
        $products->each(function ($product) use ($productsInput) {
            $product->qty = $productsInput[$product->id]['qty'];
        });

        // ✅ 5️⃣ STORE IN SESSION (THIS IS THE LINE YOU ASKED ABOUT)
        session([
            'review_products' => $products,
            'review_qty' => $productsInput->toArray()
        ]);

        // 6️⃣ Go to review page
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
