<?php

namespace App\Http\Controllers\API;

use App\Exceptions\BusinessException;
use App\Exceptions\ValidationException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\GeneratePriceListRequest;
use App\Http\Requests\WhatsApp\SendMessageRequest;
use App\Http\Requests\WhatsApp\SendProductUpdateRequest;
use App\Http\Requests\WhatsApp\SendTestMessageRequest;
use App\Http\Requests\WhatsApp\ValidateNumberRequest;
use App\Models\Customer;
use App\Services\PdfService;
use App\Services\WhatsAppService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

/**
 * WhatsApp Controller
 * 
 * Handles HTTP requests for WhatsApp operations.
 * 
 * Responsibilities:
 * - HTTP request/response handling
 * - Input validation (via FormRequest classes)
 * - Service method calls
 * - Response formatting (via ApiResponse helper)
 * - Exception handling
 * 
 * Does NOT contain:
 * - Business logic
 * - Direct model queries
 * - PDF upload logic (delegated to PdfService)
 * - Validation logic (delegated to FormRequest)
 */
class WhatsAppController extends Controller
{
    public function __construct(
        private WhatsAppService $whatsAppService,
        private PdfService $pdfService
    ) {}

    /**
     * Generate price list PDF.
     *
     * @param GeneratePriceListRequest $request
     * @return JsonResponse
     */
    public function generatePriceList(GeneratePriceListRequest $request): JsonResponse
    {
        try {
            $productIds = $request->input('product_ids', []);
            $pdfLayout = $request->input('pdf_layout', 'regular');

            $pdfPath = $this->pdfService->generatePriceList($productIds, $pdfLayout);
            $pdfUrl = $this->pdfService->getPdfUrl($pdfPath);

            return ApiResponse::success([
                'pdf_path' => $pdfPath,
                'pdf_url' => $pdfUrl,
            ], 'Price list PDF generated successfully');
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to generate PDF. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Send WhatsApp message to customers.
     *
     * @param SendMessageRequest $request
     * @return JsonResponse
     */
    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        try {
            $sendToAll = $request->boolean('send_to_all', false);
            $customerIds = $sendToAll ? null : $request->getCustomerIds();
            $message = $request->input('message_template') ?? $request->input('message');
            $includePdf = $request->boolean('include_pdf', true);
            $productIds = $request->getProductIds();
            $templateId = $request->input('template_id');
            $contentVariables = $request->getContentVariables();
            $pdfType = $request->input('pdf_type', 'regular');
            $pdfLayout = $request->input('pdf_layout', 'regular');
            $async = $request->boolean('async', true);

            // Handle custom PDF upload
            $customPdfUrl = null;
            if ($pdfType === 'custom' && $request->hasFile('custom_pdf')) {
                $customPdfUrl = $this->pdfService->uploadCustomPdf($request->file('custom_pdf'));
            }

            $results = $this->whatsAppService->sendPriceListToCustomers(
                $customerIds,
                $message,
                $includePdf,
                $productIds,
                $templateId,
                $contentVariables,
                $customPdfUrl,
                $pdfLayout,
                $async
            );

            // Handle async response
            if (isset($results['status']) && $results['status'] === 'queued') {
                return ApiResponse::success([
                    'queued' => true,
                    'customer_count' => $results['customer_count'],
                    'message' => $results['message'],
                ], $results['message']);
            }

            // Handle synchronous response
            $successCount = collect($results)->where('success', true)->count();
            $failureCount = count($results) - $successCount;

            return ApiResponse::success([
                'total' => count($results),
                'successful' => $successCount,
                'failed' => $failureCount,
                'results' => $results,
            ], "Sent to {$successCount} customer(s), {$failureCount} failed");
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getErrors(), $e->getMessage());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to send WhatsApp messages. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Send WhatsApp update with product selection (no customer selection).
     *
     * @param SendProductUpdateRequest $request
     * @return JsonResponse
     */
    public function sendProductUpdate(SendProductUpdateRequest $request): JsonResponse
    {
        try {
            $productIds = $request->getProductIds();
            $message = $request->input('message_template') ?? $request->input('message');
            $includePdf = $request->boolean('include_pdf', true);
            $templateId = $request->input('template_id');
            $contentVariables = $request->getContentVariables();
            $async = $request->boolean('async', true);

            // Send to all active customers
            $results = $this->whatsAppService->sendPriceListToCustomers(
                null, // null means all active customers
                $message,
                $includePdf,
                $productIds,
                $templateId,
                $contentVariables,
                null, // customPdfUrl
                'regular', // pdfLayout
                $async
            );

            // Handle async response
            if (isset($results['status']) && $results['status'] === 'queued') {
                return ApiResponse::success([
                    'queued' => true,
                    'customer_count' => $results['customer_count'],
                    'message' => $results['message'],
                ], $results['message']);
            }

            // Handle synchronous response
            $successCount = collect($results)->where('success', true)->count();
            $failureCount = count($results) - $successCount;

            return ApiResponse::success([
                'total' => count($results),
                'successful' => $successCount,
                'failed' => $failureCount,
                'results' => $results,
            ], "WhatsApp update sent to {$successCount} customer(s), {$failureCount} failed");
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getErrors(), $e->getMessage());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to send WhatsApp update. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Send test message to a single customer.
     *
     * @param SendTestMessageRequest $request
     * @param Customer $customer
     * @return JsonResponse
     */
    public function sendTestMessage(SendTestMessageRequest $request, Customer $customer): JsonResponse
    {
        try {
            $message = $request->input('message', 'Hello {{name}}, this is a test message from Grocery Management System.');

            $result = $this->whatsAppService->sendMessage($customer, $message);

            return ApiResponse::success($result, 'Test message sent successfully');
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getErrors(), $e->getMessage());
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Customer not found', null, 404);
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to send test message. Please try again later.',
                null,
                500
            );
        }
    }

    /**
     * Validate WhatsApp number format.
     *
     * @param ValidateNumberRequest $request
     * @return JsonResponse
     */
    public function validateNumber(ValidateNumberRequest $request): JsonResponse
    {
        try {
            $phoneNumber = $request->input('phone_number') ?? $request->input('whatsapp_number');
            $isValid = $this->whatsAppService->validateWhatsAppNumber($phoneNumber);

            return ApiResponse::success([
                'valid' => $isValid,
                'whatsapp_number' => $phoneNumber,
                'formatted' => $isValid ? $phoneNumber : null,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to validate number. Please try again later.',
                null,
                500
            );
        }
    }
}
