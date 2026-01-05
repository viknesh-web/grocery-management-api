<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ConfirmOrderRequest;
use App\Services\AddressService;
use App\Services\OrderPdfService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private OrderPdfService $pdfService,
        private AddressService $addressService
    ) {}

    public function index(Request $request)
    {
        try {
            $formData = $this->orderService->getFormData();
            $selectedQty = $this->orderService->getReviewQuantities();
            
            if (!$this->orderService->isFromReview($request->get('from'))) {
                $this->orderService->clearSession();
                $selectedQty = [];
            }
            
            return view('order.form', array_merge($formData, ['selectedQty' => $selectedQty]));
        } catch (\Exception $e) {
            Log::error('Failed to load order form', ['error' => $e->getMessage()]);
            return redirect()->route('order.form')
                ->with('error', 'Unable to load order form. Please try again.');
        }
    }

    public function review(Request $request)
    {
        try {
            $products = $this->orderService->processReview($request->products ?? []);
            $reviewQty = collect($request->products ?? [])
                ->filter(fn($p) => isset($p['qty']) && $p['qty'] > 0)
                ->toArray();
            
            $this->orderService->saveReviewToSession($products, $reviewQty);
            
            return redirect()->route('order.review.show');
        } catch (ValidationException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to process review', ['error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Unable to process review. Please try again.')
                ->withInput();
        }
    }

    public function showReview()
    {
        try {
            $products = $this->orderService->getReviewProducts();
            return view('order.review', compact('products'));
        } catch (ValidationException $e) {
            return redirect()->route('order.form')
                ->with('error', $e->getMessage());
        }
    }

    public function downloadPdf(Request $request)
    {
        try {
            $products = session('review_products');
            
            if (!$products || $products->isEmpty()) {
                return redirect()->route('order.review.show')
                    ->with('error', 'No products found for PDF generation.');
            }
            
            return $this->pdfService->generate($products);
        } catch (BusinessException $e) {
            return redirect()->route('order.review.show')
                ->with('error', $e->getMessage());
        }
    }

    public function confirm(ConfirmOrderRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $products = session('review_products');
            
            if (!$products || count($products) === 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'No products selected. Please add products to your order.',
                ], 400);
            }
            
            $orderData = [
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['email'] ?? null,
                'customer_phone' => $validated['whatsapp'],
                'customer_address' => $validated['address'],
                'products' => $products->toArray(),
                'grand_total' => $validated['grand_total'],
            ];
            
            $order = $this->orderService->createFromWebForm($orderData);
            
            // Clear review session
            $this->orderService->clearSession();
            
            // Store only order ID in session - we'll fetch data from DB
            $this->orderService->saveConfirmationToSession([
                'order_id' => $order->id,
            ]);
            
            return response()->json([
                'status' => true,
                'message' => 'Order placed successfully!',
                'redirect_url' => route('order.confirmation'),
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to create order. Please try again.',
            ], 500);
        }
    }

    public function showConfirmation()
    {
        try {
            $sessionData = $this->orderService->getConfirmationFromSession();

            if (!$sessionData || !isset($sessionData['order_id'])) {
                return redirect()->route('order.form')
                    ->with('error', 'No order confirmation found. Please start a new order.');
            }

            // Get order with customer from database
            $order = $this->orderService->find($sessionData['order_id']);

            if (!$order) {
                return redirect()->route('order.form')
                    ->with('error', 'Order not found. Please start a new order.');
            }

            // Prepare data for view
            $data = [
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'customer_name' => $order->customer?->name ?? 'N/A',
                'customer_phone' => $order->customer?->whatsapp_number ?? 'N/A',
                'customer_email' => $order->customer?->email ?? null,
                'customer_address' => $order->customer?->address ?? 'N/A',
                'total_amount' => $order->total,
            ];

            return view('order.confirmation', compact('data'));
            
        } catch (\Exception $e) {
            Log::error('Failed to show confirmation', ['error' => $e->getMessage()]);
            return redirect()->route('order.form')
                ->with('error', 'Unable to load confirmation page. Please try again.');
        }
    }

    public function searchAddress(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q');
            
            if (empty($query) || strlen($query) < 3) {
                return response()->json(['features' => []], 200);
            }

            $areas = $this->addressService->searchUAEAreas($query);
            
            $features = array_map(function ($area) {
                $formatted = $area['area'] ?? $area['full_address'] ?? '';
                $addressLine1 = trim(($area['house_number'] ?? '') . ' ' . ($area['street'] ?? ''));
                $addressLine2 = trim(($area['building'] ?? '') . ' ' . ($area['apartment'] ?? ''));
                
                return [
                    'properties' => [
                        'formatted' => $formatted,
                        'address_line1' => $addressLine1 ?: null,
                        'address_line2' => $addressLine2 ?: null,
                        'city' => $area['city'] ?? 'Dubai',
                        'country' => 'United Arab Emirates',
                    ],
                    'geometry' => [
                        'coordinates' => [0, 0],
                    ],
                ];
            }, $areas);

            return response()->json(['features' => $features], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'features' => [],
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to search address', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Unable to search addresses. Please try again.',
                'features' => [],
            ], 500);
        }
    }
}