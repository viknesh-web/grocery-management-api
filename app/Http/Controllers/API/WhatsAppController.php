<?php

namespace App\Http\Controllers\API;

use App\Exceptions\MessageException;
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
    public function generatePriceList(Request $request): JsonResponse
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

        return response()->json([
            'message' => 'Price list PDF generated successfully',
            'data' => [
                'pdf_path' => $pdfPath,
                'pdf_url' => $pdfUrl,
            ],
        ], 200);
    }

    /**
     * Send WhatsApp message to customers.
     */
    public function sendMessage(Request $request): JsonResponse
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
                return response()->json([
                    'message' => 'Invalid customer_ids format',
                    'errors' => ['customer_ids' => ['customer_ids must be an array']],
                ], 422);
            }
            foreach ($customerIds as $id) {
                if (!is_numeric($id) || !Customer::where('id', $id)->exists()) {
                    return response()->json([
                        'message' => 'Invalid customer ID',
                        'errors' => ['customer_ids' => ['One or more customer IDs are invalid']],
                    ], 422);
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
            $file = $request->file('custom_pdf');
            
            // Get original filename and extension
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension() ?: 'pdf';
            
            // Sanitize filename for filesystem safety (remove path traversal attempts, etc.)
            // Remove directory separators and null bytes
            $safeName = str_replace(['/', '\\', "\0"], '', $originalName);
            // Trim and normalize whitespace, replace spaces with hyphens, and remove unsafe characters
            $safeName = trim($safeName);
            $safeName = preg_replace('/\s+/', '-', $safeName);
            $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $safeName);
            
            // If no extension, ensure .pdf
            if (!pathinfo($safeName, PATHINFO_EXTENSION)) {
                $safeName .= '.' . $extension;
            }
            
            // Use original filename with timestamp prefix to ensure uniqueness
            $filename = date('Y-m-d_H-i-s') . '_' . $safeName;
            $path = 'pdfs/' . $filename;
            
            // Store to media disk (consistent with regular PDFs)
            $disk = \Illuminate\Support\Facades\Storage::disk('media');
            
            // Ensure pdfs directory exists
            $directory = $disk->path('pdfs');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            // Check if file already exists and append number if needed
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
            
            // Store the file
            $disk->put($path, file_get_contents($file->getRealPath()));
            
            // Verify file was stored
            if (!$disk->exists($path)) {
                throw new MessageException('Failed to store custom PDF file');
            }
            
            // Get the URL using PdfService (uses media disk)
            $customPdfUrl = $this->pdfService->getPdfUrl($path);
            // Ensure URL has no surrounding whitespace and encode spaces
            if (!empty($customPdfUrl)) {
                $customPdfUrl = trim($customPdfUrl);
                $customPdfUrl = str_replace(' ', '%20', $customPdfUrl);
            }
        }

        // Validate that template_id and message are not both provided
        if (!empty($templateId) && !empty($message)) {
            return response()->json([
                'message' => 'Cannot use both template_id and message. Please provide either template_id (for Content Template) or message (for plain text).',
                'errors' => [
                    'template_id' => ['Cannot use template with plain text message'],
                    'message' => ['Cannot use plain text message with template'],
                ],
            ], 422);
        }

        Log::debug('customPdfUrl: ' . ($customPdfUrl ?? 'null'));
        $results = $this->whatsAppService->sendPriceListToCustomers(
            $customerIds,
            $message,
            $includePdf,
            $productIds,
            $templateId,
            $contentVariables,
            $customPdfUrl,
            $pdfLayout
        );

        $successCount = collect($results)->where('success', true)->count();
        $failureCount = count($results) - $successCount;

        return response()->json([
            'message' => "Sent to {$successCount} customer(s), {$failureCount} failed",
            'data' => [
                'total' => count($results),
                'successful' => $successCount,
                'failed' => $failureCount,
                'results' => $results,
            ],
        ], 200);
    }

    /**
     * Send WhatsApp update with product selection (no customer selection).
     */
    public function sendProductUpdate(Request $request): JsonResponse
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
            return response()->json([
                'message' => 'Cannot use both template_id and message. Please provide either template_id (for Content Template) or message (for plain text).',
                'errors' => [
                    'template_id' => ['Cannot use template with plain text message'],
                    'message' => ['Cannot use plain text message with template'],
                ],
            ], 422);
        }

        // Validate that selected products match the product types (if provided)
        if (!empty($productTypes)) {
            $products = \App\Models\Product::whereIn('id', $productIds)->get();
            $invalidProducts = $products->reject(function ($product) use ($productTypes) {
                return in_array($product->product_type, $productTypes);
            });

            if ($invalidProducts->isNotEmpty()) {
                return response()->json([
                    'message' => 'Some selected products do not match the selected product types',
                    'errors' => [
                        'product_ids' => ['Selected products must match the selected product types'],
                    ],
                ], 422);
            }
        }

        // Send to all active customers
        $results = $this->whatsAppService->sendPriceListToCustomers(
            null, // null means all active customers
            $message,
            $includePdf,
            $productIds,
            $templateId,
            $contentVariables
        );

        $successCount = collect($results)->where('success', true)->count();
        $failureCount = count($results) - $successCount;

        return response()->json([
            'message' => "WhatsApp update sent to {$successCount} customer(s), {$failureCount} failed",
            'data' => [
                'total' => count($results),
                'successful' => $successCount,
                'failed' => $failureCount,
                'results' => $results,
            ],
        ], 200);
    }

    /**
     * Send test message to a single customer.
     */
    public function sendTestMessage(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $message = $request->message ?? 'Hello {{name}}, this is a test message from Grocery Management System.';

        $result = $this->whatsAppService->sendMessage($customer, $message);

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to send message',
                'error' => $result['error'],
            ], 500);
        }

        return response()->json([
            'message' => 'Test message sent successfully',
            'data' => $result,
        ], 200);
    }

    /**
     * Validate WhatsApp number format.
     */
    public function validateNumber(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => ['required', 'string'],
            'whatsapp_number' => ['sometimes', 'string'], // Alias for phone_number
        ]);

        $phoneNumber = $request->phone_number ?? $request->whatsapp_number;
        $isValid = $this->whatsAppService->validateWhatsAppNumber($phoneNumber);

        return response()->json([
            'data' => [
                'valid' => $isValid,
                'whatsapp_number' => $phoneNumber,
                'formatted' => $isValid ? $phoneNumber : null,
            ],
        ], 200);
    }
}


