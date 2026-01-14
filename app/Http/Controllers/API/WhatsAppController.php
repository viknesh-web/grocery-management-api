<?php

namespace App\Http\Controllers\API;

use App\Exceptions\BusinessException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\SendMessageRequest;
use App\Http\Requests\WhatsApp\SendProductUpdateRequest;
use App\Http\Requests\WhatsApp\ValidateNumberRequest;
use App\Services\WhatsAppService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * WhatsApp Controller
 * 
 * Handles WhatsApp integration for sending price lists and messages to customers.
 */
class WhatsAppController extends Controller
{
    public function __construct(
        private WhatsAppService $whatsAppService
    ) {}

    /**
     * Generate price list PDF.
     */
    public function generatePriceList(Request $request): JsonResponse
    {
        try {
            $productIds = $request->get('product_ids', []);

            $result = $this->whatsAppService->generatePriceList($productIds);
            
            return ApiResponse::success($result, 'Price list PDF generated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate price list PDF', null, 500);
        }
    }

    /**
     * Send WhatsApp message to customers.
     */
    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();            
           
            
            // Add custom PDF file if present
            if ($request->hasFile('custom_pdf')) {
                $data['custom_pdf_file'] = $request->file('custom_pdf');
            }
            
            $result = $this->whatsAppService->sendMessageToCustomers($data);
            
            $successCount = $result['successful'];
            $failureCount = $result['failed'];
            
            return ApiResponse::success(
                $result,
                "Sent to {$successCount} customer(s), {$failureCount} failed"
            );
        } catch (ValidationException $e) {
            Log::error('WhatsApp validation error', [
                'errors' => $e->errors(),
                'pdf_type' => $request->get('pdf_type'),
                'has_file' => $request->hasFile('custom_pdf'),
            ]);
            return ApiResponse::validationError($e->errors());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            Log::error('WhatsApp send message failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Failed to send WhatsApp messages', null, 500);
        }
    }

    /**
     * Send WhatsApp update with product selection (no customer selection).
     */
    public function sendProductUpdate(SendProductUpdateRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            $result = $this->whatsAppService->sendProductUpdate($data);
            
            $successCount = $result['successful'];
            $failureCount = $result['failed'];
            
            return ApiResponse::success(
                $result,
                "WhatsApp update sent to {$successCount} customer(s), {$failureCount} failed"
            );
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to send product update', null, 500);
        }
    }

    /**
     * Send test message to a single customer.
     */
    public function sendTestMessage(Request $request): JsonResponse
    {
        try {
            $customerId = $request->get('customer')->id;
            $message = $request->get('message');
            
            $result = $this->whatsAppService->sendTestMessage($customerId, $message);
            
            return ApiResponse::success($result, 'Test message sent successfully');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Customer not found');
        } catch (BusinessException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to send test message', null, 500);
        }
    }

    /**
     * Validate WhatsApp number format.
     */
    public function validateNumber(ValidateNumberRequest $request): JsonResponse
    {
        try {
            $phoneNumber = $request->validated()['phone_number'];
            
            $result = $this->whatsAppService->validateNumber($phoneNumber);
            
            return ApiResponse::success($result);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to validate number', null, 500);
        }
    }
}