<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Exceptions\ValidationException;
use App\Helpers\PhoneNumberHelper;
use App\Models\Customer;
use App\Repositories\CustomerRepository;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Service
 * 
 * Handles all business logic for WhatsApp operations.
 * 
 * Responsibilities:
 * - Business logic orchestration
 * - Customer selection and filtering
 * - PDF generation coordination (delegated to PdfService)
 * - Message preparation and formatting
 * - Batch operations
 * - Job dispatching
 * - Phone number normalization (via PhoneNumberHelper)
 * 
 * Does NOT contain:
 * - Direct Twilio API calls (delegated to TwilioService)
 * - Direct customer queries (uses CustomerRepository)
 * - PDF generation logic (delegated to PdfService)
 */
class WhatsAppService
{
    public function __construct(
        private TwilioService $twilioService,
        private PdfService $pdfService,
        private CustomerRepository $customerRepository
    ) {}

    /**
     * Send price list to customers.
     * 
     * Handles:
     * - PDF generation (delegated to PdfService)
     * - Customer selection (via CustomerRepository)
     * - Batch job dispatching (if async)
     * - Synchronous sending (if not async)
     * - Message preparation
     *
     * @param array|null $customerIds Specific customer IDs (null = all active customers)
     * @param string|null $customMessage Custom message text
     * @param bool $includePdf Whether to include PDF
     * @param array|null $productIds Product IDs for PDF generation
     * @param string|null $templateId Twilio Content Template ID
     * @param array|null $contentVariables Variables for Content Template
     * @param string|null $customPdfUrl Custom PDF URL (if uploaded)
     * @param string $pdfLayout PDF layout ('regular' or 'catalog')
     * @param bool $async Whether to send asynchronously
     * @return array Result array with status, message, and results
     */
    public function sendPriceListToCustomers(
        ?array $customerIds = null,
        ?string $customMessage = null,
        bool $includePdf = true,
        ?array $productIds = null,
        ?string $templateId = null,
        ?array $contentVariables = null,
        ?string $customPdfUrl = null,
        string $pdfLayout = 'regular',
        bool $async = true
    ): array {
        // Generate PDF if needed (business logic - determine when to generate)
        $pdfUrl = null;
        if ($includePdf) {
            $pdfUrl = $this->preparePdfUrl($customPdfUrl, $productIds, $pdfLayout);
        }

        // If async, dispatch batch job (business logic - async vs sync)
        if ($async) {
            return $this->dispatchBatchJob(
                $customerIds,
                $customMessage,
                $pdfUrl,
                $templateId,
                $contentVariables
            );
        }

        // Synchronous sending (business logic - process customers)
        return $this->sendToCustomersSynchronously(
            $customerIds,
            $customMessage,
            $pdfUrl,
            $templateId,
            $contentVariables
        );
    }

    /**
     * Send a WhatsApp message to a single customer.
     * 
     * Handles:
     * - Phone number normalization (via PhoneNumberHelper)
     * - Message preparation (template vs plain text)
     * - Twilio API call (delegated to TwilioService)
     * - Error handling and logging
     *
     * @param Customer $customer
     * @param string|null $message Plain text message
     * @param string|null $pdfUrl PDF URL to attach
     * @param string|null $templateId Twilio Content Template ID
     * @param array|null $contentVariables Variables for Content Template
     * @return array Result array with success status, message_sid, and status
     * @throws ValidationException If phone number is invalid
     * @throws ServiceException If message sending fails
     */
    public function sendMessage(
        Customer $customer,
        ?string $message = null,
        ?string $pdfUrl = null,
        ?string $templateId = null,
        ?array $contentVariables = null
    ): array {
        try {
            // Normalize and format phone numbers (business logic - phone number handling)
            $fromNumber = $this->twilioService->getWhatsAppNumber();
            $toNumber = PhoneNumberHelper::formatForWhatsApp($customer->whatsapp_number);
            
            if (empty($toNumber)) {
                throw new ValidationException(
                    'Invalid customer WhatsApp number format',
                    ['whatsapp_number' => ['Invalid WhatsApp number format']]
                );
            }

            Log::info('Sending WhatsApp message', [
                'from' => $fromNumber,
                'to' => $toNumber,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'using_template' => !empty($templateId),
                'template_id' => $templateId,
            ]);

            // Prepare message data (business logic - message formatting)
            // Replace {{name}} placeholder with customer name if message is provided
            $processedMessage = $message ? str_replace('{{name}}', $customer->name, $message) : null;
            
            $messageData = $this->prepareMessageData(
                $processedMessage,
                $templateId,
                $contentVariables,
                $pdfUrl,
                $fromNumber,
                $customer->name
            );

            // Send message via Twilio (delegated to TwilioService)
            $twilioResponse = $this->twilioService->sendWhatsAppMessage($toNumber, $messageData);

            Log::info('WhatsApp message sent successfully', [
                'message_sid' => $twilioResponse['sid'],
                'status' => $twilioResponse['status'],
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'to' => $toNumber,
            ]);

            return [
                'success' => true,
                'message_sid' => $twilioResponse['sid'],
                'status' => $twilioResponse['status'],
            ];
        } catch (ValidationException $e) {
            // Re-throw validation exceptions
            throw $e;
        } catch (ServiceException $e) {
            // Re-throw service exceptions (already logged by TwilioService)
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('WhatsApp message failed (Unexpected Error)', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_whatsapp' => $customer->whatsapp_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMessage = config('app.debug') 
                ? $e->getMessage() 
                : 'Failed to send WhatsApp message. Please try again later.';

            throw new ServiceException($errorMessage);
        }
    }

    /**
     * Validate WhatsApp number format.
     * 
     * Delegates to PhoneNumberHelper for validation.
     *
     * @param string $number
     * @return bool
     */
    public function validateWhatsAppNumber(string $number): bool
    {
        return PhoneNumberHelper::validate($number);
    }

    /**
     * Prepare PDF URL for sending.
     * 
     * Business logic: Determines whether to use custom PDF or generate new one.
     *
     * @param string|null $customPdfUrl
     * @param array|null $productIds
     * @param string $pdfLayout
     * @return string|null
     */
    protected function preparePdfUrl(?string $customPdfUrl, ?array $productIds, string $pdfLayout): ?string
    {
        if ($customPdfUrl !== null) {
            // Use custom uploaded PDF
            return $customPdfUrl;
        }

        // Generate PDF from products (delegated to PdfService)
        $pdfPath = $this->pdfService->generatePriceList($productIds ?? [], $pdfLayout);
        return $this->pdfService->getPdfUrl($pdfPath);
    }

    /**
     * Dispatch batch job for async sending.
     * 
     * Business logic: Queues messages for background processing.
     *
     * @param array|null $customerIds
     * @param string|null $customMessage
     * @param string|null $pdfUrl
     * @param string|null $templateId
     * @param array|null $contentVariables
     * @return array
     */
    protected function dispatchBatchJob(
        ?array $customerIds,
        ?string $customMessage,
        ?string $pdfUrl,
        ?string $templateId,
        ?array $contentVariables
    ): array {
        \App\Jobs\WhatsAppMessageBatch::dispatch(
            $customerIds,
            $customMessage,
            $pdfUrl,
            $templateId,
            $contentVariables
        );

        // Get customer count (business logic - determine count)
        $customerCount = $customerIds 
            ? count($customerIds) 
            : $this->customerRepository->countByFilters(['status' => 'active']);

        return [
            'status' => 'queued',
            'message' => "Messages queued for {$customerCount} customer(s)",
            'customer_count' => $customerCount,
        ];
    }

    /**
     * Send messages to customers synchronously.
     * 
     * Business logic: Processes customers in chunks and sends messages.
     *
     * @param array|null $customerIds
     * @param string|null $customMessage
     * @param string|null $pdfUrl
     * @param string|null $templateId
     * @param array|null $contentVariables
     * @return array
     */
    protected function sendToCustomersSynchronously(
        ?array $customerIds,
        ?string $customMessage,
        ?string $pdfUrl,
        ?string $templateId,
        ?array $contentVariables
    ): array {
        $results = [];
        
        // Process customers in chunks to avoid memory issues (business logic - chunking)
        // Use query builder chunking for memory efficiency
        if ($customerIds === null || empty($customerIds)) {
            // Send to all active customers in chunks
            Customer::where('status', 'active')
                ->chunk(100, function ($customers) use (&$results, $customMessage, $pdfUrl, $templateId, $contentVariables) {
                    $this->processCustomerChunk($customers, $results, $customMessage, $pdfUrl, $templateId, $contentVariables);
                });
        } else {
            // Process specific customer IDs in chunks
            Customer::whereIn('id', $customerIds)
                ->chunk(100, function ($customers) use (&$results, $customMessage, $pdfUrl, $templateId, $contentVariables) {
                    $this->processCustomerChunk($customers, $results, $customMessage, $pdfUrl, $templateId, $contentVariables);
                });
        }

        return $results;
    }

    /**
     * Process a chunk of customers and send messages.
     * 
     * Business logic: Handles individual customer sending with error handling.
     *
     * @param \Illuminate\Support\Collection $customers
     * @param array $results Reference to results array
     * @param string|null $customMessage
     * @param string|null $pdfUrl
     * @param string|null $templateId
     * @param array|null $contentVariables
     * @return void
     */
    protected function processCustomerChunk(
        \Illuminate\Support\Collection $customers,
        array &$results,
        ?string $customMessage,
        ?string $pdfUrl,
        ?string $templateId,
        ?array $contentVariables
    ): void {
        foreach ($customers as $customer) {
            try {
                $result = $this->sendMessage($customer, $customMessage, $pdfUrl, $templateId, $contentVariables);
                $results[] = array_merge([
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                ], $result);
            } catch (\Throwable $e) {
                // Log individual customer error but continue with others
                Log::warning('Failed to send WhatsApp message to customer', [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    /**
     * Prepare message data for Twilio API.
     * 
     * Business logic: Formats message data based on template vs plain text.
     *
     * @param string|null $message Plain text message (with {{name}} already replaced)
     * @param string|null $templateId Twilio Content Template ID
     * @param array|null $contentVariables Variables for Content Template
     * @param string|null $pdfUrl PDF URL to attach
     * @param string $fromNumber Sender WhatsApp number
     * @param string|null $customerName Customer name for default message replacement
     * @return array Message data for Twilio API
     */
    protected function prepareMessageData(
        ?string $message,
        ?string $templateId,
        ?array $contentVariables,
        ?string $pdfUrl,
        string $fromNumber,
        ?string $customerName = null
    ): array {
        $messageData = [
            'from' => $fromNumber,
        ];

        if (!empty($templateId)) {
            // Use Twilio Content Template
            $messageData['contentSid'] = $templateId;
            
            // Add content variables if provided
            if (!empty($contentVariables) && is_array($contentVariables)) {
                $messageData['contentVariables'] = json_encode($contentVariables);
            }
        } else {
            // Use plain text message
            $messageBody = $message ?? str_replace(
                '{{name}}',
                $customerName ?? '',
                config('services.twilio.default_message', 'Hello {{name}}, here is today\'s price list.')
            );
            $messageData['body'] = $messageBody;
        }
        
        // Add PDF attachment if provided
        if ($pdfUrl) {
            $messageData['mediaUrl'] = [$pdfUrl];
        }

        return $messageData;
    }
}
