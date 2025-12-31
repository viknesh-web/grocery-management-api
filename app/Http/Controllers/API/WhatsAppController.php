<?php

namespace App\Http\Controllers\API;

use App\Exceptions\BusinessException;
use App\Exceptions\ValidationException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\PdfService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Controller
 * 
 * Handles WhatsApp integration for sending price lists and messages to customers.
 */
class WhatsAppController extends Controller
{
    protected WhatsAppService $whatsAppService;
    protected PdfService $pdfService;

    public function __construct(WhatsAppService $whatsAppService, PdfService $pdfService)
    {
        $this->whatsAppService = $whatsAppService;
        $this->pdfService = $pdfService;
    }

    /**
     * Decode JSON-encoded FormData fields into native arrays so Laravel
     * validation that expects arrays works when clients send JSON strings.
     */
    private function normalizeJsonFields(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            $value = $request->get($field);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge([$field => $decoded]);
                }
            }
        }
    }

    /**
     * Generate price list PDF.
     */
    public function generatePriceList(Request $request)
    {
        $request->validate([
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
            'pdf_layout' => ['sometimes', 'string', 'in:regular,catalog'],
        ]);

        $productIds = $request->get('product_ids', []);
        $pdfLayout = $request->get('pdf_layout', 'regular');

        $pdfPath = $this->pdfService->generatePriceList($productIds, $pdfLayout);
        $pdfUrl = $this->pdfService->getPdfUrl($pdfPath);

        return ApiResponse::success([
            'pdf_path' => $pdfPath,
            'pdf_url' => $pdfUrl,
        ], 'Price list PDF generated successfully');
    }

    /**
     * Send WhatsApp message to customers.
     */
    public function sendMessage(Request $request)
    {
        // Handle JSON-encoded arrays from FormData
        $pdfType = $request->get('pdf_type', 'regular');
        $pdfLayout = $request->get('pdf_layout', 'regular');
        
        $sendToAll = $request->boolean('send_to_all', false);

        // Normalize any JSON-encoded fields (sent as strings via FormData)
        $this->normalizeJsonFields($request, ['product_ids', 'customer_ids', 'product_types', 'content_variables']);
        
        $validationRules = [
            'send_to_all' => ['sometimes', 'boolean'],
            'customer_ids' => $sendToAll ? ['nullable'] : ['required'],
            'message_template' => ['nullable', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:1000'], // Alias for message_template
            'include_pdf' => ['required'],
            'template_id' => ['nullable', 'string'], // Twilio Content Template ID
            'content_variables' => ['nullable'],
            'pdf_type' => ['sometimes', 'string', 'in:regular,custom'],
        ];

        // Conditional validation based on PDF type
        if ($pdfType === 'regular') {
            $validationRules['product_ids'] = ['required', 'array', 'min:1'];
            $validationRules['product_ids.*'] = ['required', 'integer', 'exists:products,id'];
        } else {
            $validationRules['custom_pdf'] = ['required', 'file', 'mimes:pdf', 'max:10240']; // Max 10MB
        }

        $request->validate($validationRules);

        // Parse JSON-encoded arrays from FormData
        $customerIdsJson = $request->get('customer_ids');
        $customerIds = null;
        if ($customerIdsJson) {
            if (is_string($customerIdsJson)) {
                $customerIds = json_decode($customerIdsJson, true);
            } else {
                $customerIds = $customerIdsJson;
            }
        }

        $productIdsJson = $request->get('product_ids');
        $productIds = null;
        if ($productIdsJson) {
            if (is_string($productIdsJson)) {
                $productIds = json_decode($productIdsJson, true);
            } else {
                $productIds = $productIdsJson;
            }
        }

        $productTypesJson = $request->get('product_types');
        $productTypes = [];
        if ($productTypesJson) {
            if (is_string($productTypesJson)) {
                $productTypes = json_decode($productTypesJson, true) ?? [];
            } else {
                $productTypes = $productTypesJson ?? [];
            }
        }

        $contentVariablesJson = $request->get('content_variables');
        $contentVariables = null;
        if ($contentVariablesJson) {
            if (is_string($contentVariablesJson)) {
                $contentVariables = json_decode($contentVariablesJson, true);
            } else {
                $contentVariables = $contentVariablesJson;
            }
        }

        // Validate customer_ids if provided and not sending to all
        if (!$sendToAll && $customerIds !== null && !empty($customerIds)) {
            if (!is_array($customerIds)) {
                return ApiResponse::validationError(
                    ['customer_ids' => ['customer_ids must be an array']],
                    'Invalid customer_ids format'
                );
            }
            foreach ($customerIds as $id) {
                if (!is_numeric($id) || !Customer::where('id', $id)->exists()) {
                    return ApiResponse::validationError(
                        ['customer_ids' => ['One or more customer IDs are invalid']],
                        'Invalid customer ID'
                    );
                }
            }
        }
        
        // Set customerIds to null if sending to all
        $customerIds = $sendToAll ? null : $customerIds;
        $message = $request->message_template ?? $request->message;
        $includePdf = $request->boolean('include_pdf', true);
        $templateId = $request->template_id;
        
        // Handle custom PDF upload
        $customPdfUrl = null;
        if ($pdfType === 'custom' && $request->hasFile('custom_pdf')) {
            $customPdfUrl = $this->uploadCustomPdf($request->file('custom_pdf'));
        }

        // Validate that template_id and message are not both provided
        if (!empty($templateId) && !empty($message)) {
            return ApiResponse::validationError(
                [
                    'template_id' => ['Cannot use template with plain text message'],
                    'message' => ['Cannot use plain text message with template'],
                ],
                'Cannot use both template_id and message. Please provide either template_id (for Content Template) or message (for plain text).'
            );
        }

        // Check if async sending is requested (default: true)
        $async = $request->boolean('async', true);

        $results = $this->whatsAppService->sendPriceListToCustomers(
            $customerIds,
            $message,
            $includePdf,
            $productIds,
            $templateId,
            $contentVariables,
            $customPdfUrl,
            $pdfLayout,
            $async // Pass async flag
        );

        // Handle async response
        if (isset($results['status']) && $results['status'] === 'queued') {
            return ApiResponse::success([
                'queued' => true,
                'customer_count' => $results['customer_count'],
                'message' => $results['message'],
            ], $results['message']);
        }

        // Handle synchronous response (existing code)
        $successCount = collect($results)->where('success', true)->count();
        $failureCount = count($results) - $successCount;

        return ApiResponse::success([
            'total' => count($results),
            'successful' => $successCount,
            'failed' => $failureCount,
            'results' => $results,
        ], "Sent to {$successCount} customer(s), {$failureCount} failed");
    }

    /**
     * Send WhatsApp update with product selection (no customer selection).
     */
    public function sendProductUpdate(Request $request)
    {
        // Normalize JSON-encoded fields that may be sent as strings via FormData
        $this->normalizeJsonFields($request, ['product_ids', 'product_types', 'content_variables']);

        $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
            'product_types' => ['sometimes', 'array'],
            'product_types.*' => ['required', 'string', 'in:daily,standard'],
            'message_template' => ['nullable', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:1000'], // Alias for message_template
            'include_pdf' => ['required'],
            'template_id' => ['nullable', 'string'], // Twilio Content Template ID
            'content_variables' => ['nullable', 'array'], // Variables for Content Template
        ]);

        $productIds = $request->product_ids;
        $productTypes = $request->product_types ?? [];
        $message = $request->message_template ?? $request->message;
        $includePdf = $request->boolean('include_pdf', true);
        $templateId = $request->template_id;
        $contentVariables = $request->content_variables;

        // Validate that template_id and message are not both provided
        if (!empty($templateId) && !empty($message)) {
            return ApiResponse::validationError(
                [
                    'template_id' => ['Cannot use template with plain text message'],
                    'message' => ['Cannot use plain text message with template'],
                ],
                'Cannot use both template_id and message. Please provide either template_id (for Content Template) or message (for plain text).'
            );
        }

        // Validate that selected products match the product types (if provided)
        if (!empty($productTypes)) {
            $products = \App\Models\Product::whereIn('id', $productIds)->get();
            $invalidProducts = $products->reject(function ($product) use ($productTypes) {
                return in_array($product->product_type, $productTypes);
            });

            if ($invalidProducts->isNotEmpty()) {
                return ApiResponse::validationError(
                    ['product_ids' => ['Selected products must match the selected product types']],
                    'Some selected products do not match the selected product types'
                );
            }
        }

        // Check if async sending is requested (default: true)
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
            $async // Pass async flag
        );

        // Handle async response
        if (isset($results['status']) && $results['status'] === 'queued') {
            return ApiResponse::success([
                'queued' => true,
                'customer_count' => $results['customer_count'],
                'message' => $results['message'],
            ], $results['message']);
        }

        // Handle synchronous response (existing code)
        $successCount = collect($results)->where('success', true)->count();
        $failureCount = count($results) - $successCount;

        return ApiResponse::success([
            'total' => count($results),
            'successful' => $successCount,
            'failed' => $failureCount,
            'results' => $results,
        ], "WhatsApp update sent to {$successCount} customer(s), {$failureCount} failed");
    }

    /**
     * Send test message to a single customer.
     */
    public function sendTestMessage(Request $request, Customer $customer)
    {
        $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $message = $request->message ?? 'Hello {{name}}, this is a test message from Grocery Management System.';

        $result = $this->whatsAppService->sendMessage($customer, $message);

        if (!$result['success']) {
            return ApiResponse::error(
                $result['error'] ?? 'Failed to send message',
                $result,
                500
            );
        }

        return ApiResponse::success($result, 'Test message sent successfully');
    }

    /**
     * Upload and store a custom PDF file securely.
     *
     * @param UploadedFile $file
     * @return string PDF URL
     * @throws ValidationException
     * @throws BusinessException
     */
    private function uploadCustomPdf(UploadedFile $file): string
    {
        // Validate file size (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if ($file->getSize() > $maxSize) {
            throw new ValidationException(
                'PDF file size exceeds maximum allowed size of 10MB',
                ['file' => ['The PDF file must not exceed 10MB']]
            );
        }

        // Validate MIME type
        $allowedMimeTypes = ['application/pdf'];
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new ValidationException(
                'Invalid file type. Only PDF files are allowed.',
                ['file' => ['Only PDF files are allowed']]
            );
        }

        // Validate file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'pdf') {
            throw new ValidationException(
                'Invalid file extension. Only PDF files are allowed.',
                ['file' => ['Only PDF files are allowed']]
            );
        }

        // Get original filename and sanitize it
        $originalName = $file->getClientOriginalName();
        
        // Remove path traversal attempts and null bytes
        $safeName = str_replace(['/', '\\', "\0", "\r", "\n"], '', $originalName);
        
        // Trim and normalize whitespace
        $safeName = trim($safeName);
        
        // Replace spaces with hyphens
        $safeName = preg_replace('/\s+/', '-', $safeName);
        
        // Remove all characters except alphanumeric, hyphens, underscores, and dots
        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $safeName);
        
        // Ensure filename is not empty
        if (empty($safeName)) {
            $safeName = 'uploaded-pdf';
        }
        
        // Ensure .pdf extension
        if (!pathinfo($safeName, PATHINFO_EXTENSION)) {
            $safeName .= '.pdf';
        }
        
        // Limit filename length (max 255 characters including extension)
        if (strlen($safeName) > 200) {
            $pathInfo = pathinfo($safeName);
            $nameWithoutExt = substr($pathInfo['filename'], 0, 200 - strlen($pathInfo['extension']) - 1);
            $safeName = $nameWithoutExt . '.' . $pathInfo['extension'];
        }
        
        // Generate unique filename with timestamp and random string
        $timestamp = date('Y-m-d_H-i-s');
        $randomString = bin2hex(random_bytes(4)); // 8 character random string
        $filename = $timestamp . '_' . $randomString . '_' . $safeName;
        $path = 'pdfs/' . $filename;
        
        // Store to media disk (consistent with regular PDFs)
        $disk = Storage::disk('media');
        
        // Ensure pdfs directory exists
        $directory = $disk->path('pdfs');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Check if file already exists (unlikely with random string, but check anyway)
        $counter = 1;
        $baseFilename = $filename;
        while ($disk->exists($path)) {
            $pathInfo = pathinfo($baseFilename);
            $nameWithoutExt = $pathInfo['filename'];
            $ext = $pathInfo['extension'] ?? 'pdf';
            $filename = $nameWithoutExt . '_' . $counter . '.' . $ext;
            $path = 'pdfs/' . $filename;
            $counter++;
        }
        
        // Store the file using Laravel's safe file storage
        try {
            $stored = $disk->putFileAs('pdfs', $file, $filename);
            
            if (!$stored) {
                throw new BusinessException('Failed to store PDF file. Please try again.');
            }
            
            // Verify file was stored and is readable
            if (!$disk->exists($path)) {
                throw new BusinessException('Failed to verify PDF file storage. Please try again.');
            }
            
            // Get the URL using PdfService (uses media disk)
            $pdfUrl = $this->pdfService->getPdfUrl($path);
            
            // Ensure URL has no surrounding whitespace and encode spaces
            if (empty($pdfUrl)) {
                throw new BusinessException('Failed to generate PDF URL. Please try again.');
            }
            
            $pdfUrl = trim($pdfUrl);
            $pdfUrl = str_replace(' ', '%20', $pdfUrl);
            
            Log::info('Custom PDF uploaded successfully', [
                'filename' => $filename,
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $mimeType,
            ]);
            
            return $pdfUrl;
        } catch (\Exception $e) {
            Log::error('Failed to upload custom PDF', [
                'error' => $e->getMessage(),
                'filename' => $filename,
            ]);
            
            throw new BusinessException('Failed to upload PDF file. Please try again.');
        }
    }

    /**
     * Validate WhatsApp number format.
     */
    public function validateNumber(Request $request)
    {
        $request->validate([
            'phone_number' => ['required', 'string'],
            'whatsapp_number' => ['sometimes', 'string'], // Alias for phone_number
        ]);

        $phoneNumber = $request->phone_number ?? $request->whatsapp_number;
        $isValid = $this->whatsAppService->validateWhatsAppNumber($phoneNumber);

        return ApiResponse::success([
            'valid' => $isValid,
            'whatsapp_number' => $phoneNumber,
            'formatted' => $isValid ? $phoneNumber : null,
        ]);
    }
}


