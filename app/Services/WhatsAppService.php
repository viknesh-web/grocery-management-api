<?php

namespace App\Services;

use App\Models\Customer;
use App\Services\PdfService;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;

class WhatsAppService
{
    protected Client $twilio;
    protected PdfService $pdfService;
    protected string $whatsappNumber;

    public function __construct(PdfService $pdfService)
    {
        $this->pdfService = $pdfService;
        $this->twilio = new Client(
            config('services.twilio.account_sid'),
            config('services.twilio.auth_token')
        );
        // Ensure WhatsApp number has the correct format
        $whatsappNumber = config('services.twilio.whatsapp_number');
        if (!str_starts_with($whatsappNumber, 'whatsapp:')) {
            // Keep the + sign, Twilio WhatsApp requires whatsapp:+14155238886 format
            $whatsappNumber = 'whatsapp:' . $whatsappNumber;
        }
        $this->whatsappNumber = $whatsappNumber;
    }

    public function sendPriceListToCustomers(?array $customerIds = null, ?string $customMessage = null, bool $includePdf = true, ?array $productIds = null, ?string $templateId = null, ?array $contentVariables = null, ?string $customPdfUrl = null): array
    {
        $results = [];
        $pdfUrl = null;
        
        // Generate PDF if needed
        if ($includePdf) {
            if ($customPdfUrl !== null) {
                // Use custom uploaded PDF
                $pdfUrl = $customPdfUrl;
            } else {
                // Generate PDF from products
                $pdfPath = $this->pdfService->generatePriceList($productIds ?? []);
                $pdfUrl = $this->pdfService->getPdfUrl($pdfPath);
            }
        }

        // If no customer IDs provided, send to all active customers
        if ($customerIds === null || empty($customerIds)) {
            $customers = Customer::where('active', true)->get();
        } else {
            $customers = Customer::whereIn('id', $customerIds)->get();
        }

        foreach ($customers as $customer) {
            $result = $this->sendMessage($customer, $customMessage, $pdfUrl, $templateId, $contentVariables);
            $results[] = array_merge([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
            ], $result);
        }

        return $results;
    }

    public function sendMessage(Customer $customer, ?string $message = null, ?string $pdfUrl = null, ?string $templateId = null, ?array $contentVariables = null): array
    {
        try {
            // Ensure WhatsApp number format is correct (already formatted in constructor)
            $fromNumber = $this->whatsappNumber;

            // Ensure customer WhatsApp number format is correct
            $toNumber = $customer->whatsapp_number;
            if (!str_starts_with($toNumber, 'whatsapp:')) {
                // Remove any spaces or dashes, ensure it starts with +
                $cleaned = preg_replace('/[^\d+]/', '', $toNumber);
                if (!str_starts_with($cleaned, '+')) {
                    // If it's a 10-digit Indian number, add +91
                    if (strlen($cleaned) === 10) {
                        $cleaned = '+91' . $cleaned;
                    } else {
                        $cleaned = '+' . $cleaned;
                    }
                }
                $toNumber = 'whatsapp:' . $cleaned;
            }

            Log::info('Sending WhatsApp message', [
                'from' => $fromNumber,
                'to' => $toNumber,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'using_template' => !empty($templateId),
                'template_id' => $templateId,
            ]);

            // Build message data - conditional logic for template vs plain text
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
                // Use plain text message (existing behavior)
                $messageBody = $message ?? str_replace(
                    '{{name}}',
                    $customer->name,
                    config('services.twilio.default_message', 'Hello {{name}}, here is today\'s price list.')
                );
                $messageData['body'] = $messageBody;
            }
            
            if ($pdfUrl) {
               $messageData['mediaUrl'] = [$pdfUrl];
            }

            
            $message = $this->twilio->messages->create(
                $toNumber,
                $messageData
            );

            Log::info('WhatsApp message sent successfully', [
                'message_sid' => $message->sid,
                'status' => $message->status,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'to' => $toNumber,
            ]);

            return [
                'success' => true,
                'message_sid' => $message->sid,
                'status' => $message->status,
            ];
        } catch (\Twilio\Exceptions\RestException $e) {
            // Twilio-specific error handling
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
            
            // Provide user-friendly error messages for common Twilio errors
            if ($errorCode === 21211) {
                $errorMessage = 'Invalid recipient phone number. Please check the customer\'s WhatsApp number format.';
            } elseif ($errorCode === 21608) {
                $errorMessage = 'Recipient has not joined the Twilio WhatsApp sandbox. They need to send "join [code]" to +14155238886 first.';
            } elseif ($errorCode === 21614) {
                $errorMessage = 'WhatsApp number is not registered with Twilio. Please verify your Twilio WhatsApp configuration.';
            } elseif ($errorCode === 21217) {
                $errorMessage = 'Invalid "from" phone number. Please check your Twilio WhatsApp number configuration.';
            } elseif ($errorCode === 20003) {
                $errorMessage = 'Twilio authentication failed. Please check your Account SID and Auth Token.';
            }
            
            Log::error('WhatsApp message failed (Twilio Error)', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_whatsapp' => $customer->whatsapp_number,
                'from_number' => $this->whatsappNumber,
                'to_number' => $toNumber ?? 'not formatted',
                'error' => $e->getMessage(),
                'error_code' => $errorCode,
                'twilio_status' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : null,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'error_code' => $errorCode,
                'twilio_error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp message failed (General Error)', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_whatsapp' => $customer->whatsapp_number,
                'from_number' => $this->whatsappNumber,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }

    public function validateWhatsAppNumber(string $number): bool
    {
        // Remove any non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $number);

        // Check if it starts with + and has valid format
        return preg_match('/^\+[1-9]\d{1,14}$/', $cleaned) === 1;
    }
}


