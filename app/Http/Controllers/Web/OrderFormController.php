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

/**
 * Order Form Controller
 * 
 * Handles HTTP requests for order form operations (web views).
 * 
 * Responsibilities:
 * - HTTP request/response handling
 * - View rendering
 * - Redirect handling
 * - Input validation (via FormRequest classes)
 * - Service method calls
 * - Exception handling
 * 
 * Does NOT contain:
 * - Business logic
 * - Direct model queries
 * - Direct session manipulation (delegated to OrderService)
 * - Direct API calls (delegated to AddressService)
 * - PDF generation logic (delegated to OrderPdfService)
 */
class OrderFormController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private OrderPdfService $pdfService,
        private AddressService $addressService
    ) {}

    /**
     * Show order form.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function show(Request $request)
    {
        try {
            $formData = $this->orderService->getFormData();
            $selectedQty = $this->orderService->getReviewQuantities();
            
            // Clear session if not coming from review page
            if (!$this->orderService->isFromReview($request->get('from'))) {
                $this->orderService->clearSession();
                $selectedQty = [];
            }
            
            return view('order.form', array_merge($formData, ['selectedQty' => $selectedQty]));
        } catch (\Exception $e) {
            return redirect()->route('order.form')
                ->with('error', 'Unable to load order form. Please try again.');
        }
    }

    /**
     * Show order review page.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showReview()
    {
        try {
            $products = $this->orderService->getReviewProducts();
            return view('order.review', compact('products'));
        } catch (ValidationException $e) {
            return redirect()->route('order.form')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('order.form')
                ->with('error', 'Unable to load review page. Please try again.');
        }
    }

    /**
     * Download order PDF.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response
     */
    public function downloadPdf(Request $request)
    {
        try {
            // Get products from session (delegated to service)
            $products = session('review_products');
            return $this->pdfService->generate($products);
        } catch (BusinessException $e) {
            return redirect()->route('order.review.show')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('order.review.show')
                ->with('error', 'Unable to generate PDF. Please try again.');
        }
    }

    /**
     * Process order review.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function review(Request $request)
    {
        try {
            $products = $this->orderService->processReview($request->products ?? []);
            $reviewQty = collect($request->products ?? [])
                ->filter(fn($p) => isset($p['qty']) && $p['qty'] > 0)
                ->toArray();
            
            $this->orderService->saveReviewToSession($products, $reviewQty);
            
            return view('order.review', compact('products'));
        } catch (ValidationException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Unable to process review. Please try again.')
                ->withInput();
        }
    }

    /**
     * Confirm order.
     *
     * @param ConfirmOrderRequest $request
     * @return \Illuminate\View\View|JsonResponse
     */
    public function confirm(ConfirmOrderRequest $request)
    {
        try {
            $data = $request->validated();
            
            // Get products from session
            $products = session('review_products');
            if (!$products || (is_countable($products) && count($products) === 0)) {
                if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'No products selected. Please select products first.',
                    ], 400);
                }
                return redirect()->route('order.form')->with('error', 'No products selected');
            }
            
            // Prepare order data
            $orderData = [
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['email'] ?? null,
                'customer_phone' => $data['whatsapp'],
                'customer_address' => $data['address'],
                'products' => is_countable($products) ? $products : $products->toArray(),
                'grand_total' => $data['grand_total'],
                'subtotal' => session('order_subtotal', $data['grand_total']),
                'notes' => $request->notes ?? null,
            ];
            
            // Create order in database
            $order = $this->orderService->createFromWebForm($orderData);
            
            // Clear session
            $this->orderService->clearSession();
            session()->forget(['order_subtotal']);
            
            // Store for confirmation page
            $this->orderService->saveConfirmationToSession([
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'total_amount' => $order->total_amount,
                'order_id' => $order->id,
            ]);
            
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'status' => true,
                    'data' => [
                        'order_number' => $order->order_number,
                        'order_id' => $order->id,
                    ],
                    'redirect_url' => route('order.confirmation.show'),
                ]);
            }

            return redirect()->route('order.confirmation.show');
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to create order. Please try again.',
                ], 500);
            }
            
            return redirect()->back()
                ->with('error', 'Failed to create order. Please try again.')
                ->withInput();
        }
    }

    /**
     * Show order confirmation page.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showConfirmation(Request $request)
    {
        try {
            $data = $this->orderService->getConfirmationFromSession();

            if (!$data) {
                return redirect()->route('order.form')
                    ->with('error', 'No order confirmation found. Please start a new order.');
            }

            return view('order.confirmation', compact('data'));
        } catch (\Exception $e) {
            return redirect()->route('order.form')
                ->with('error', 'Unable to load confirmation page. Please try again.');
        }
    }

    /**
     * Search UAE addresses using Geoapify API.
     * 
     * Returns addresses in Geoapify format for frontend compatibility.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function geoapifyAddress(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q');
            
            if (empty($query)) {
                return response()->json(['features' => []], 200);
            }

            $areas = $this->addressService->searchUAEAreas($query);
            
            // Transform to Geoapify format for frontend compatibility
            // Frontend expects: { features: [{ properties: { formatted, address_line1, ... }, geometry: { coordinates: [...] } }] }
            // AddressService returns: ['area', 'city', 'emirate', 'full_address', 'street', 'house_number', 'building', 'apartment', 'area_base']
            $features = array_map(function ($area) {
                // Use 'area' (display area) as formatted address, fallback to 'full_address'
                $formatted = $area['area'] ?? $area['full_address'] ?? '';
                
                // Build address_line1 from street and house_number
                $addressLine1 = trim(($area['house_number'] ?? '') . ' ' . ($area['street'] ?? ''));
                
                // Build address_line2 from building and apartment
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
                        'coordinates' => [0, 0], // Coordinates not available in mapped format
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
            return response()->json([
                'error' => 'Unable to search addresses. Please try again.',
                'features' => [],
            ], 500);
        }
    }
}
