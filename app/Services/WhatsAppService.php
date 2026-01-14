<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Customer;
use App\Models\Product;
use App\Repositories\CustomerRepository;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;

/**
 * WhatsApp Service
 * 
 * Handles WhatsApp messaging operations using Twilio API.
 */
class WhatsAppService extends BaseService
{
    protected Client $twilio;
    protected string $whatsappNumber;

    public function __construct(
        private PdfService $pdfService,
        private CustomerRepository $customerRepository
    ) {
        $this->initializeTwilioClient();
    }

    /**
     * Initialize Twilio client and WhatsApp number.
     */
    private function initializeTwilioClient(): void
    {
        $this->twilio = new Client(
            config('services.twilio.account_sid'),
            config('services.twilio.auth_token')
        );
        
        // Ensure WhatsApp number has the correct format
        $whatsappNumber = config('services.twilio.whatsapp_number');
        if (!str_starts_with($whatsappNumber, 'whatsapp:')) {
            $whatsappNumber = 'whatsapp:' . $whatsappNumber;
        }
        $this->whatsappNumber = $whatsappNumber;
    }

    /**
     * Generate price list PDF.
     */
    public function generatePriceList(array $productIds = [], ?string $pdfLayout = 'regular'): array
    {
        return $this->handle(function () use ($productIds, $pdfLayout) {
            $pdfPath = $this->pdfService->generatePriceList($productIds, $pdfLayout);
            $pdfUrl = $this->pdfService->getPdfUrl($pdfPath);

            return [
                'pdf_path' => $pdfPath,
                'pdf_url' => $pdfUrl,
            ];
        }, 'Failed to generate price list PDF');
    }

    /**
     * Send WhatsApp message to customers.
     */
    public function sendMessageToCustomers(array $data): array
    {
        return $this->handle(function () use ($data) {
            $customerIds = $data['customer_ids'] ?? null;
            $sendToAll = $data['send_to_all'] ?? false;
            $message = $data['message_template'] ?? $data['message'] ?? null;
            $includePdf = $data['include_pdf'] ?? true;
            $productIds = $data['product_ids'] ?? null;
            $templateId = $data['template_id'] ?? null;
            $contentVariables = $data['content_variables'] ?? null;
            $pdfType = $data['pdf_type'] ?? 'regular';
            $pdfLayout = $data['pdf_layout'] ?? 'regular';
            $customPdfUrl = null;

            // Handle custom PDF upload using PdfService
            if ($pdfType === 'custom' && isset($data['custom_pdf_file'])) {
                $customPdfUrl = $this->pdfService->uploadCustomPdf($data['custom_pdf_file']);
            }

            // Validate template usage
            $this->validateTemplateUsage($templateId, $message);

            // Generate or handle PDF
            $pdfUrl = $this->preparePdfUrl($includePdf, $pdfType, $customPdfUrl, $productIds, $pdfLayout);

            // Get customers
            $customers = $this->getCustomers($customerIds, $sendToAll);

            // Send messages
            $results = $this->sendMessagesToCustomers(
                $customers,
                $message,
                $pdfUrl,
                $templateId,
                $contentVariables
            );

            return $this->formatResults($results);
        }, 'Failed to send WhatsApp messages');
    }

    /**
     * Send product update to all customers.
     */
    public function sendProductUpdate(array $data): array
    {
        return $this->handle(function () use ($data) {
            $productIds = $data['product_ids'] ?? [];
            $productTypes = $data['product_types'] ?? [];
            $message = $data['message_template'] ?? $data['message'] ?? null;
            $includePdf = $data['include_pdf'] ?? true;
            $templateId = $data['template_id'] ?? null;
            $contentVariables = $data['content_variables'] ?? null;
            $pdfLayout = $data['pdf_layout'] ?? 'regular';

            // Validate template usage
            $this->validateTemplateUsage($templateId, $message);

            // Validate product types if provided
            if (!empty($productTypes) && !empty($productIds)) {
                $this->validateProductTypes($productIds, $productTypes);
            }

            // Generate PDF if needed
            $pdfUrl = null;
            if ($includePdf && !empty($productIds)) {
                $pdfPath = $this->pdfService->generatePriceList($productIds, $pdfLayout);
                $pdfUrl = $this->pdfService->getPdfUrl($pdfPath);
            }

            // Get all customers (match old behavior - no status filter)
            $customers = Customer::all();

            // Send messages
            $results = $this->sendMessagesToCustomers(
                $customers,
                $message,
                $pdfUrl,
                $templateId,
                $contentVariables
            );

            return $this->formatResults($results);
        }, 'Failed to send product update');
    }

    /**
     * Send test message to a single customer.
     */
    public function sendTestMessage(int $customerId, ?string $message = null): array
    {
        return $this->handle(function () use ($customerId, $message) {
            $customer = $this->customerRepository->findOrFail($customerId);

            $messageText = $message ?? str_replace(
                '{{name}}',
                $customer->name,
                'Hello {{name}}, this is a test message from Grocery Management System.'
            );

            $result = $this->sendSingleMessage($customer, $messageText);

            if (!$result['success']) {
                throw new BusinessException($result['error']);
            }

            return $result;
        }, 'Failed to send test message');
    }

    /**
     * Validate WhatsApp number format.
     */
    public function validateNumber(string $phoneNumber): array
    {
        return $this->handle(function () use ($phoneNumber) {
            $isValid = $this->validateWhatsAppNumber($phoneNumber);

            return [
                'valid' => $isValid,
                'whatsapp_number' => $phoneNumber,
                'formatted' => $isValid ? $this->formatWhatsAppNumber($phoneNumber) : null,
            ];
        }, 'Failed to validate WhatsApp number');
    }

    /**
     * Validate that template and message are not both provided.
     */
    private function validateTemplateUsage(?string $templateId, ?string $message): void
    {
        if (!empty($templateId) && !empty($message)) {
            throw new BusinessException(
                'Cannot use both template_id and message. Please provide either template_id (for Content Template) or message (for plain text).',
                [
                    'template_id' => ['Cannot use template with plain text message'],
                    'message' => ['Cannot use plain text message with template'],
                ]
            );
        }
    }

    /**
     * Validate product types match selected products.
     */
    private function validateProductTypes(array $productIds, array $productTypes): void
    {
        $products = Product::whereIn('id', $productIds)->get();
        
        $invalidProducts = $products->reject(function ($product) use ($productTypes) {
            return in_array($product->product_type, $productTypes);
        });

        if ($invalidProducts->isNotEmpty()) {
            throw new BusinessException(
                'Some selected products do not match the selected product types',
                ['product_ids' => ['Selected products must match the selected product types']]
            );
        }
    }

    /**
     * Prepare PDF URL based on type.
     */
    private function preparePdfUrl(bool $includePdf, string $pdfType, ?string $customPdfUrl, ?array $productIds, ?string $pdfLayout): ?string
    {
        if (!$includePdf) {
            return null;
        }

        if ($pdfType === 'custom' && $customPdfUrl) {
            return $customPdfUrl;
        }

        if (!empty($productIds)) {
            $pdfPath = $this->pdfService->generatePriceList($productIds, $pdfLayout ?? 'regular');
            return $this->pdfService->getPdfUrl($pdfPath);
        }

        return null;
    }

    /**
     * Get customers based on selection criteria.
     */
    private function getCustomers(?array $customerIds, bool $sendToAll): \Illuminate\Database\Eloquent\Collection
    {
        if ($sendToAll) {
            // Match old behavior: get ALL customers without status filter
            return Customer::all();
        }

        if (empty($customerIds)) {
            throw new BusinessException('No customers selected');
        }

        return $this->customerRepository->query()
            ->whereIn('id', $customerIds)
            ->get();
    }

    /**
     * Send messages to multiple customers.
     */
    private function sendMessagesToCustomers(
        \Illuminate\Database\Eloquent\Collection $customers,
        ?string $message,
        ?string $pdfUrl,
        ?string $templateId,
        ?array $contentVariables
    ): array {
        $results = [];

        foreach ($customers as $customer) {
            $result = $this->sendSingleMessage(
                $customer,
                $message,
                $pdfUrl,
                $templateId,
                $contentVariables
            );
            
            $results[] = array_merge([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
            ], $result);
        }

        return $results;
    }

    /**
     * Send message to a single customer.
     */
    private function sendSingleMessage(
        Customer $customer,
        ?string $message = null,
        ?string $pdfUrl = null,
        ?string $templateId = null,
        ?array $contentVariables = null
    ): array {
        try {
            $toNumber = $this->formatWhatsAppNumber($customer->whatsapp_number);

            Log::info('Sending WhatsApp message', [
                'from' => $this->whatsappNumber,
                'to' => $toNumber,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'using_template' => !empty($templateId),
                'template_id' => $templateId,
                'has_pdf' => !empty($pdfUrl),
            ]);

            print_r("Sending WhatsApp message to {$pdfUrl}\n");
            // Build message data
            $messageData = $this->buildMessageData($message, $pdfUrl, $templateId, $contentVariables, $customer);

            // Send message via Twilio
            $twilioMessage = $this->twilio->messages->create($toNumber, $messageData);

            Log::info('WhatsApp message sent successfully', [
                'message_sid' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
            ]);

            return [
                'success' => true,
                'message_sid' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
            ];
        } catch (RestException $e) {
            return $this->handleTwilioException($e, $customer, $toNumber ?? null);
        } catch (\Exception $e) {
            return $this->handleGeneralException($e, $customer);
        }
    }

    /**
     * Build message data for Twilio API.
     */
    private function buildMessageData(
        ?string $message,
        ?string $pdfUrl,
        ?string $templateId,
        ?array $contentVariables,
        Customer $customer
    ): array {
        $messageData = ['from' => $this->whatsappNumber];

        if (!empty($templateId)) {
            // Use Twilio Content Template
            $messageData['contentSid'] = $templateId;
            
            if (!empty($contentVariables) && is_array($contentVariables)) {
                $messageData['contentVariables'] = json_encode($contentVariables);
            }
        } else {
            // Use plain text message
            $messageBody = $message ?? str_replace(
                '{{name}}',
                $customer->name,
                config('services.twilio.default_message', 'Hello {{name}}, here is today\'s price list.')
            );
            $messageData['body'] = $messageBody;
        }

        if ($pdfUrl) {
            $messageData['mediaUrl'] = [$pdfUrl];
            
            Log::debug('WhatsApp message data prepared', [
                'pdf_url' => $pdfUrl,
                'message_data' => $messageData,
            ]);
        }

        return $messageData;
    }

    /**
     * Handle Twilio REST exceptions.
     */
    private function handleTwilioException(RestException $e, Customer $customer, ?string $toNumber): array
    {
        $errorCode = $e->getCode();
        $errorMessage = $this->getTwilioErrorMessage($errorCode, $e->getMessage());
        
        Log::error('WhatsApp message failed (Twilio Error)', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_whatsapp' => $customer->whatsapp_number,
            'from_number' => $this->whatsappNumber,
            'to_number' => $toNumber,
            'error' => $e->getMessage(),
            'error_code' => $errorCode,
        ]);

        return [
            'success' => false,
            'error' => $errorMessage,
            'error_code' => $errorCode,
            'twilio_error' => $e->getMessage(),
        ];
    }

    /**
     * Handle general exceptions.
     */
    private function handleGeneralException(\Exception $e, Customer $customer): array
    {
        Log::error('WhatsApp message failed (General Error)', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_whatsapp' => $customer->whatsapp_number,
            'error' => $e->getMessage(),
            'error_code' => $e->getCode(),
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'error_code' => $e->getCode(),
        ];
    }

    /**
     * Get user-friendly error message for Twilio error codes.
     */
    private function getTwilioErrorMessage(int $errorCode, string $defaultMessage): string
    {
        return match ($errorCode) {
            21211 => 'Invalid recipient phone number. Please check the customer\'s WhatsApp number format.',
            21608 => 'Recipient has not joined the Twilio WhatsApp sandbox. They need to send "join [code]" to +14155238886 first.',
            21614 => 'WhatsApp number is not registered with Twilio. Please verify your Twilio WhatsApp configuration.',
            21217 => 'Invalid "from" phone number. Please check your Twilio WhatsApp number configuration.',
            20003 => 'Twilio authentication failed. Please check your Account SID and Auth Token.',
            63038 => 'Daily message limit exceeded. Your Twilio account has reached the maximum number of messages allowed per day.',
            default => $defaultMessage,
        };
    }

    /**
     * Format WhatsApp number to Twilio format.
     */
    private function formatWhatsAppNumber(string $number): string
    {
        if (str_starts_with($number, 'whatsapp:')) {
            return $number;
        }

        // Remove any spaces or dashes
        $cleaned = preg_replace('/[^\d+]/', '', $number);
        
        // Ensure it starts with +
        if (!str_starts_with($cleaned, '+')) {
            // If it's a 10-digit Indian number, add +91
            if (strlen($cleaned) === 10) {
                $cleaned = '+91' . $cleaned;
            } else {
                $cleaned = '+' . $cleaned;
            }
        }
        
        return 'whatsapp:' . $cleaned;
    }

    /**
     * Validate WhatsApp number format.
     */
    private function validateWhatsAppNumber(string $number): bool
    {
        $cleaned = preg_replace('/[^\d+]/', '', $number);

        return preg_match('/^\+[1-9]\d{1,14}$/', $cleaned) === 1;
    }

    /**
     * Format results summary.
     */
    private function formatResults(array $results): array
    {
        $successCount = collect($results)->where('success', true)->count();
        $failureCount = count($results) - $successCount;

        return [
            'total' => count($results),
            'successful' => $successCount,
            'failed' => $failureCount,
            'results' => $results,
        ];
    }
}