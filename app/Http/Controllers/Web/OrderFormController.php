<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\OrderPdfService;
use App\Services\OrderService;
use App\Validator\OrderValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderFormController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function show(Request $request)
    {
        $formData = $this->orderService->getFormData();
        $selectedQty = session('review_qty', []);
        
        if ($request->get('from') !== 'review') {
            $this->orderService->clearSession();
            $selectedQty = [];
        }
        
        return view('order.form', array_merge($formData, ['selectedQty' => $selectedQty]));
    }

    public function showReview()
    {
        try {
            $products = $this->orderService->getReviewProducts();
            return view('order.review', compact('products'));
        } catch (\Exception $e) {
            return redirect()->route('order.form')->with('error', $e->getMessage());
        }
    }

    public function downloadPdf(Request $request, OrderPdfService $pdfService)
    {
        $products = session('review_products');
        return $pdfService->generate($products);
    }

    public function review(Request $request)
    {
        try {
            $products = $this->orderService->processReview($request->products);
            $reviewQty = collect($request->products)->filter(fn($p) => isset($p['qty']) && $p['qty'] > 0)->toArray();
            
            $this->orderService->saveReviewToSession($products, $reviewQty);
            
            return view('order.review', compact('products'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function confirm(Request $request)
    {
        $validator = Validator::make($request->all(), OrderValidator::onConfirm(), OrderValidator::messages());
        $data = $validator->validate();
        
        $this->orderService->clearSession();

        if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
            session(['order_confirmation' => $data]);
            
            $response = [
                'status' => true,
                'data' => $data,
                'redirect_url' => route('order.confirmation.show'),
            ];

            return response()->json($response);
        }

        return view('order.confirmation', compact('data'));
    }

    public function showConfirmation(Request $request)
    {
        $data = session('order_confirmation');

        if (!$data) {
            return redirect()->route('order.form');
        }

        return view('order.confirmation', compact('data'));
    }

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

