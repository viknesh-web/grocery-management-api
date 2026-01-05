<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ConfirmOrderRequest;
use App\Models\Product;
use App\Services\AddressService;
use App\Services\CartService;
use App\Services\OrderPdfService;
use App\Services\OrderService;
use App\Services\PriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Order Controller - Updated for CartService Integration
 * 
 * Changes from original:
 * - Added CartService and PriceCalculator dependencies
 * - Replaced session-based review with cart-based review
 * - Pass UNIT_CONVERSIONS to views (not directly in JS)
 * - Cleaner error handling
 */
class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private OrderPdfService $pdfService,
        private AddressService $addressService,
        private CartService $cartService,
        private PriceCalculator $priceCalculator
    ) {}

    /**
     * Show order form.
     * 
     * UPDATED: Pass UNIT_CONVERSIONS to view
     */
    public function index(Request $request)
    {
        try {
            $formData = $this->orderService->getFormData();
            $selectedQty = $this->orderService->getReviewQuantities();
            
            // Clear cart if not coming from review page
            if (!$this->orderService->isFromReview($request->get('from'))) {
                $this->orderService->clearSession();
                $selectedQty = [];
            }
            
            // UPDATED: Pass unit conversions to view
            $unitConversions = Product::UNIT_CONVERSIONS;
            
            return view('order.form', array_merge($formData, [
                'selectedQty' => $selectedQty,
                'unitConversions' => $unitConversions, // ← Pass to view
            ]));
        } catch (\Exception $e) {
            Log::error('Failed to load order form', ['error' => $e->getMessage()]);
            return redirect()->route('order.form')
                ->with('error', 'Unable to load order form. Please try again.');
        }
    }

    /**
     * Process review (form submission).
     * 
     * UPDATED: Now uses CartService instead of session
     */
    public function review(Request $request)
    {
        try {
            // Process and save to cart (returns cart ID)
            $this->orderService->processReview($request->products ?? []);
            
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

    /**
     * Show review page.
     * 
     * UPDATED: Gets products from CartService
     */
    public function showReview()
    {
        try {
            // Get products from cart (fresh from DB)
            $products = $this->orderService->getReviewProducts();
            
            if ($products->isEmpty()) {
                return redirect()->route('order.form')
                    ->with('error', 'Cart is empty. Please add products.');
            }
            
            return view('order.review', compact('products'));
            
        } catch (ValidationException $e) {
            return redirect()->route('order.form')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to show review', ['error' => $e->getMessage()]);
            return redirect()->route('order.form')
                ->with('error', 'Unable to load review page. Please try again.');
        }
    }

    /**
     * Download PDF of order.
     * 
     * UPDATED: Gets products from CartService
     */
    public function downloadPdf(Request $request)
    {
        try {
            // Get products from cart (not session)
            $products = $this->orderService->getReviewProducts();
            
            if ($products->isEmpty()) {
                return redirect()->route('order.review.show')
                    ->with('error', 'No products found for PDF generation.');
            }
            
            return $this->pdfService->generate($products);
            
        } catch (ValidationException $e) {
            return redirect()->route('order.review.show')
                ->with('error', $e->getMessage());
        } catch (BusinessException $e) {
            return redirect()->route('order.review.show')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to generate PDF', ['error' => $e->getMessage()]);
            return redirect()->route('order.review.show')
                ->with('error', 'Unable to generate PDF. Please try again.');
        }
    }

    /**
     * Confirm order (AJAX endpoint).
     * 
     * UPDATED: Gets products from CartService
     */
    public function confirm(ConfirmOrderRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // Get products from cart (not session)
            $products = $this->orderService->getReviewProducts();
            
            if ($products->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart is empty. Please add products to your order.',
                ], 400);
            }
            
            // Prepare order data
            $orderData = [
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['email'] ?? null,
                'customer_phone' => $validated['whatsapp'],
                'customer_address' => $validated['address'],
                'products' => $products, // Pass collection (OrderService handles it)
                'grand_total' => $validated['grand_total'],
            ];
            
            // Create order (cart is cleared automatically inside service)
            $order = $this->orderService->createFromWebForm($orderData);
            
            // Store order ID for confirmation page
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

    /**
     * Show order confirmation page.
     * 
     * UNCHANGED - works as before
     */
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

    /**
     * Search UAE addresses (AJAX endpoint).
     * 
     * UNCHANGED - works as before
     */
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