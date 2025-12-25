<?php

namespace App\Http\Controllers\API\V1;
    
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\OrderPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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
        $products = session('review_products');

        return $pdfService->generate($products);
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
        $request->validate([
            'customer_name' => [
                'required',
                'min:3',
                'max:100',
                'regex:/^[a-zA-Z\s]+$/'
            ],
            'whatsapp' => [
                'required',
                'regex:/^[0-9+\-\s]+$/',
                'regex:/^(\+91|91)?[6-9][0-9]{9}$|^(\+971|971)?[0-9]{9}$/'
            ],
            'email'       => 'nullable|email',
            'address' => [
                'required',
                'min:2',
                function ($attr, $value, $fail) {
                    if (!str_contains(strtolower($value), 'dubai')) {
                        $fail('Please select a valid Dubai address');
                    }
                }
            ],
 
            'grand_total' => 'required|numeric|min:1',
        ], [
            'customer_name.regex' => 'Name should contain only letters',
            'whatsapp.regex'      => 'Only Indian (+91) and UAE (+971) numbers are allowed',
 
        ]);
        $data = $request->all();
        session()->forget(['review_products', 'review_qty']);

        if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
            // persist confirmation data in session so the web GET route can render the view
            session(['order_confirmation' => $data]);

            return response()->json([
                'status' => true,
                'data' => $data,
                'redirect_url' => route('order.confirmation.show'),
            ]);
        }

        return view('order.confirmation', compact('data'));
    }
        

    /**
     * Show confirmation page (GET) using session data set after successful POST.
     */
    public function showConfirmation(Request $request)
    {
        $data = session('order_confirmation');

        if (!$data) {
            return redirect()->route('order.form');
        }

        return view('order.confirmation', compact('data'));
    }
      

    /**
     * Proxy Geoapify autocomplete request.
     *
     * Returns the `features` array from Geoapify or an empty array on failure.
     */
    public function geoapifyAddress(Request $request)
    {
        $q = $request->get('q');

        $response = Http::get('https://api.geoapify.com/v1/geocode/autocomplete', [
            'text' => $q,
            'country' => 'ae',
            'city' => 'Dubai',
            'limit' => 5,
            'apiKey' => config('services.geoapify.key'),
        ]);

        Log::debug('Geoapify response code: ' . $response->status());

        return $response->json()['features'] ?? [];
    }
}
