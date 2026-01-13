<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ConfirmOrderRequest;
use App\Models\Product;
use App\Models\User;
use App\Repositories\CustomerRepository;
use App\Services\AddressService;
use App\Services\CartService;
use App\Services\OrderPdfService;
use App\Services\OrderService;
use App\Services\PriceCalculator;
use App\Validator\OrderValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Order Controller - Using Signed URLs (No Token Storage Required)
 */
class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private OrderPdfService $pdfService,
        private AddressService $addressService,
        private CartService $cartService,
        private PriceCalculator $priceCalculator,
        private CustomerRepository $customerRepository
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
            
            $unitConversions = Product::UNIT_CONVERSIONS;

            $productMinQuantities = [];
            foreach ($formData['products'] as $product) {
                if ($product->hasMinimumQuantity()) {
                    $productMinQuantities[$product->id] = [
                        'min_qty' => $product->min_quantity,
                        'unit' => $product->stock_unit,
                        'display' => $product->getMinimumQuantityDisplay(),
                    ];
                }
            }
            
            $isAdmin = $request->boolean('is_admin', false);
            $adminUserId = $request->get('admin_user_id');
            $signature = $request->get('signature');
            $adminUser = null;
            $customers = [];
            $selectedCustomerId = null;
            
            if ($isAdmin && $adminUserId && $signature) {
                // Validate signed URL
                if ($request->hasValidSignature()) {
                    // Load admin user
                    $adminUser = User::find((int) $adminUserId);
                    
                    if ($adminUser && $adminUser->master) {
                        // Load active customers for dropdown
                        $customers = $this->customerRepository->query()
                            ->where('status', 'active')
                            ->select('id', 'name', 'whatsapp_number', 'address')
                            ->orderBy('name', 'asc')
                            ->get()
                            ->map(function ($customer) {
                                return [
                                    'id' => $customer->id,
                                    'name' => $customer->name,
                                    'phone' => $customer->whatsapp_number,
                                    'address' => $customer->address ?? '',
                                ];
                            });
                        
                        // Restore selected customer from session if coming back from review
                        if ($this->orderService->isFromReview($request->get('from'))) {
                            $selectedCustomerId = session('selected_customer_id');
                            
                            Log::info('Restoring selected customer from session', [
                                'admin_id' => $adminUser->id,
                                'selected_customer_id' => $selectedCustomerId,
                            ]);
                        }
                        
                        Log::info('Admin order creation initiated (signed URL)', [
                            'admin_id' => $adminUser->id,
                            'admin_email' => $adminUser->email,
                            'customers_count' => $customers->count(),
                            'selected_customer_id' => $selectedCustomerId,
                        ]);
                    } else {
                        $isAdmin = false;
                        Log::warning('Admin user not found or not master', [
                            'user_id' => $adminUserId,
                        ]);
                    }
                } else {
                    // Invalid or expired signature
                    $isAdmin = false;
                    Log::warning('Invalid or expired signed URL');
                }
            }
            
            return view('order.form', array_merge($formData, [
                'selectedQty' => $selectedQty,
                'unitConversions' => $unitConversions,
                'productMinQuantities' => $productMinQuantities,
                'isAdmin' => $isAdmin,
                'adminUserId' => $adminUserId,
                'adminUser' => $adminUser,
                'customers' => $customers,
                'selectedCustomerId' => $selectedCustomerId,
            ]));
            
        } catch (\Exception $e) {
            Log::error('Failed to load order form', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('order.form')
                ->with('error', 'Unable to load order form. Please try again.');
        }
    }

    /**
     * Process review (form submission).
     */
    public function review(Request $request)
    {
        try {
            if ($request->boolean('is_admin')) {
                session([
                    'admin_order' => true,
                    'admin_user_id' => $request->get('admin_user_id'),
                    'selected_customer_id' => $request->get('selected_customer_id'),
                ]);
                
                Log::info('Admin order review', [
                    'admin_user_id' => $request->get('admin_user_id'),
                    'selected_customer_id' => $request->get('selected_customer_id'),
                ]);
            }

            $products = $request->products ?? [];
            
            // Validate minimum quantities
            $minQtyValidation = OrderValidator::validateProductQuantities($products);
            
            if (!$minQtyValidation['valid']) {
                $errorMessage = 'Minimum quantity requirements not met for some products: ' . 
                    implode(', ', $minQtyValidation['errors']);
                
                return redirect()->back()
                    ->with('error', $errorMessage)
                    ->with('min_qty_errors', $minQtyValidation['errors'])
                    ->withInput();
            }
            
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
     */
    public function showReview()
    {
        try {
            $products = $this->orderService->getReviewProducts();
            
            if ($products->isEmpty()) {
                return redirect()->route('order.form')
                    ->with('error', 'Cart is empty. Please add products.');
            }
            
            $isAdmin = session('admin_order', false);
            $adminUserId = session('admin_user_id');
            $selectedCustomerId = session('selected_customer_id');
            
            $selectedCustomer = null;
            $backToProductsUrl = route('order.form', ['from' => 'review']);
            
            if ($isAdmin && $adminUserId) {
                // Validate admin user exists
                $adminUser = User::find((int) $adminUserId);
                
                if ($adminUser && $adminUser->master) {
                    // Generate new signed URL for "Back to Products"
                    $backToProductsUrl = URL::temporarySignedRoute(
                        'order.form',
                        now()->addHour(),
                        [
                            'is_admin' => 1,
                            'admin_user_id' => $adminUser->id,
                            'from' => 'review',
                        ]
                    );
                    
                    if ($selectedCustomerId) {
                        $selectedCustomer = $this->customerRepository->find((int) $selectedCustomerId);
                        
                        Log::info('Loading selected customer for admin order', [
                            'customer_id' => $selectedCustomerId,
                            'customer_found' => $selectedCustomer ? true : false,
                        ]);
                    }
                    
                    Log::info('Generated back to products URL for admin', [
                        'admin_id' => $adminUser->id,
                    ]);
                } else {
                    // Invalid admin user, clear admin session
                    $isAdmin = false;
                    session()->forget(['admin_order', 'admin_user_id', 'selected_customer_id']);
                    
                    Log::warning('Admin user invalid in review, clearing session', [
                        'admin_user_id' => $adminUserId,
                    ]);
                }
            }
            
            return view('order.review', [
                'products' => $products,
                'is_admin' => $isAdmin,
                'admin_user_id' => $adminUserId,
                'selected_customer' => $selectedCustomer,
                'back_to_products_url' => $backToProductsUrl,
            ]);
            
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
     */
    public function downloadPdf(Request $request)
    {
        try {
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
 
    public function confirm(ConfirmOrderRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $products = $this->orderService->getReviewProducts();
            
            if ($products->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart is empty. Please add products to your order.',
                ], 400);
            }

            $rawCartItems = $this->cartService->getRawCartItems();
            $minQtyValidation = OrderValidator::validateProductQuantities($rawCartItems);
            
            if (!$minQtyValidation['valid']) {
                return response()->json([
                    'status' => false,
                    'message' => 'Minimum quantity requirements not met',
                    'errors' => $minQtyValidation['errors'],
                ], 422);
            }
            
            
            // Calculate grand total on backend using PriceCalculator
            $items = $products->map(fn($p) => [
                'product' => $p,
                'quantity' => $p->cart_qty ?? $p->qty ?? 0,
                'unit' => $p->cart_unit ?? $p->stock_unit,
            ])->toArray();
            
            $totals = $this->priceCalculator->calculateCartTotal($items);
            $grandTotal = $totals['total'];
            
            // Validate that cart has items with value
            if ($grandTotal <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order total must be greater than zero.',
                ], 400);
            }
            
            Log::info('Order total calculated', [
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'total' => $grandTotal,
            ]);
            
            $orderData = [
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['email'] ?? null,
                'customer_phone' => $validated['whatsapp'],
                'customer_address' => $validated['address'],
                'products' => $products,
                'grand_total' => $grandTotal, // Use calculated total from backend
            ];
            
            // Check if this is an admin order
            $isAdmin = $request->boolean('is_admin', false);
            $adminUserId = $request->get('admin_user_id');
            $selectedCustomerId = $request->get('selected_customer_id');
            
            if ($isAdmin && $adminUserId) {
                // Validate admin user
                $adminUser = User::find((int) $adminUserId);
                
                if ($adminUser && $adminUser->master) {
                    $orderData['created_by_admin'] = $adminUser->id;
                    
                    if ($selectedCustomerId) {
                        $orderData['customer_id'] = (int) $selectedCustomerId;
                    }
                    
                    Log::info('Admin order confirmation', [
                        'admin_id' => $adminUser->id,
                        'selected_customer_id' => $selectedCustomerId,
                        'calculated_total' => $grandTotal,
                    ]);
                }
            }
            
            $order = $this->orderService->createFromWebForm($orderData);
            
            // Save order ID and admin context to session for confirmation page
            $confirmationData = [
                'order_id' => $order->id,
            ];
            
            // Preserve admin context for "Order Again" button
            if ($isAdmin && $adminUser) {
                $confirmationData['admin_user_id'] = $adminUser->id;
                
                Log::info('Preserving admin context for confirmation page', [
                    'admin_id' => $adminUser->id,
                    'order_id' => $order->id,
                ]);
            }
            
            $this->orderService->saveConfirmationToSession($confirmationData);
            
            // Now clear the temporary admin session data
            session()->forget(['admin_order', 'admin_user_id', 'selected_customer_id']);
            
            return response()->json([
                'status' => true,
                'message' => 'Order placed successfully!',
                'redirect_url' => route('order.confirmation'),
                'order_total' => $grandTotal,
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

            $order = $this->orderService->find($sessionData['order_id']);

            if (!$order) {
                return redirect()->route('order.form')
                    ->with('error', 'Order not found. Please start a new order.');
            }

            $data = [
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'customer_name' => $order->customer?->name ?? 'N/A',
                'customer_phone' => $order->customer?->whatsapp_number ?? 'N/A',
                'customer_email' => $order->customer?->email ?? null,
                'customer_address' => $order->customer?->address ?? 'N/A',
                'total_amount' => $order->total,
            ];
            
            // Check if this was an admin order and generate new signed URL for "Order Again"
            $adminOrderUrl = null;
            $isAdminOrder = false;
            
            if (isset($sessionData['admin_user_id']) && $sessionData['admin_user_id']) {
                $adminUser = User::find($sessionData['admin_user_id']);
                
                if ($adminUser && $adminUser->master) {
                    $isAdminOrder = true;
                    
                    // Generate new signed URL for "Order Again"
                    $adminOrderUrl = URL::temporarySignedRoute(
                        'order.form',
                        now()->addHour(),
                        [
                            'is_admin' => 1,
                            'admin_user_id' => $adminUser->id,
                        ]
                    );
                    
                    Log::info('Generated new admin order URL for "Order Again"', [
                        'admin_id' => $adminUser->id,
                        'order_id' => $order->id,
                    ]);
                }
            }

            return view('order.confirmation', compact('data', 'adminOrderUrl', 'isAdminOrder'));
            
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
                $full_address = $area['full_address'];
                $formatted = $area['area'] ?? $area['full_address'] ?? '';
                $addressLine1 = trim(($area['house_number'] ?? '') . ' ' . ($area['street'] ?? ''));
                $addressLine2 = trim(($area['building'] ?? '') . ' ' . ($area['apartment'] ?? ''));
                
                return [
                    'properties' => [
                        'full_address' => $full_address,
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