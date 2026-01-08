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
 */
class WhatsAppService
{
    public function __construct(
        private TwilioService $twilioService,
        private PdfService $pdfService,
        private CustomerRepository $customerRepository
    ) {}

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
        $pdfUrl = null;
        if ($includePdf) {
            $pdfUrl = $this->preparePdfUrl($customPdfUrl, $productIds, $pdfLayout);
        }

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

    public function validateWhatsAppNumber(string $number): bool
    {
        return PhoneNumberHelper::validate($number);
    }

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
 
    protected function sendToCustomersSynchronously(
        ?array $customerIds,
        ?string $customMessage,
        ?string $pdfUrl,
        ?string $templateId,
        ?array $contentVariables
    ): array {
        $results = [];
        
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
