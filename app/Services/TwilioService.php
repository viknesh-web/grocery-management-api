<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;

/**
 * Twilio Service
 * 
 * Handles all Twilio API interactions.
 * 
 * Responsibilities:
 * - Twilio client initialization
 * - Message sending via Twilio API
 * - Twilio-specific error handling
 * - Error code mapping to user-friendly messages
 */
class TwilioService
{
    protected Client $client;
    protected string $whatsappNumber;

    public function __construct()
    {
        $this->client = new Client(
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

    /**
     * Get the configured WhatsApp number.
     *
     * @return string
     */
    public function getWhatsAppNumber(): string
    {
        return $this->whatsappNumber;
    }

    /**
     * Send a WhatsApp message via Twilio API.
     * 
     * Handles:
     * - Message creation via Twilio API
     * - Template vs plain text messages
     * - Media attachments (PDFs)
     * - Twilio-specific error handling
     *
     * @param string $toNumber Recipient WhatsApp number (formatted with whatsapp: prefix)
     * @param array $messageData Message data (body, contentSid, contentVariables, mediaUrl)
     * @return array Twilio message response with sid and status
     * @throws ServiceException
     */
    public function sendWhatsAppMessage(string $toNumber, array $messageData): array
    {
        try {
            // Ensure 'from' is set
            $messageData['from'] = $messageData['from'] ?? $this->whatsappNumber;

            // Send message via Twilio API
            $message = $this->client->messages->create($toNumber, $messageData);

            return [
                'sid' => $message->sid,
                'status' => $message->status,
            ];
        } catch (RestException $e) {
            // Map Twilio error codes to user-friendly messages
            $errorMessage = $this->mapTwilioError($e);
            
            Log::error('Twilio API error', [
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
                'to_number' => $toNumber,
                'from_number' => $this->whatsappNumber,
                'status_code' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : null,
            ]);

            throw new ServiceException($errorMessage);
        } catch (\Exception $e) {
            Log::error('Twilio service error', [
                'error' => $e->getMessage(),
                'to_number' => $toNumber,
                'from_number' => $this->whatsappNumber,
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMessage = config('app.debug') 
                ? $e->getMessage() 
                : 'Failed to send WhatsApp message. Please try again later.';

            throw new ServiceException($errorMessage);
        }
    }

    /**
     * Map Twilio error codes to user-friendly error messages.
     * 
     * Business logic: Translates technical Twilio errors to user-friendly messages.
     *
     * @param RestException $e
     * @return string User-friendly error message
     */
    protected function mapTwilioError(RestException $e): string
    {
        $errorCode = $e->getCode();

        return match ($errorCode) {
            21211 => 'Invalid recipient phone number. Please check the customer\'s WhatsApp number format.',
            21608 => 'Recipient has not joined the Twilio WhatsApp sandbox. They need to send "join [code]" to +14155238886 first.',
            21614 => 'WhatsApp number is not registered with Twilio. Please verify your Twilio WhatsApp configuration.',
            21217 => 'Invalid "from" phone number. Please check your Twilio WhatsApp number configuration.',
            20003 => 'Twilio authentication failed. Please check your Account SID and Auth Token.',
            default => 'Failed to send WhatsApp message. Please try again later.',
        };
    }

    /**
     * Validate Twilio configuration.
     * 
     * Checks if Twilio credentials are configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty(config('services.twilio.account_sid')) &&
               !empty(config('services.twilio.auth_token')) &&
               !empty(config('services.twilio.whatsapp_number'));
    }
}

