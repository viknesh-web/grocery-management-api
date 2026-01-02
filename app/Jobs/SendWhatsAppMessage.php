<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Customer $customer,
        public ?string $message,
        public ?string $pdfUrl = null,
        public ?string $templateId = null,
        public ?array $contentVariables = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsAppService): void
    {
        try {
            $result = $whatsAppService->sendMessage(
                $this->customer,
                $this->message,
                $this->pdfUrl,
                $this->templateId,
                $this->contentVariables
            );

            // sendMessage returns array with 'success' => true on success
            // or throws exception on failure
            if (isset($result['success']) && $result['success']) {
                Log::info('WhatsApp message sent successfully', [
                    'customer_id' => $this->customer->id,
                    'message_sid' => $result['message_sid'] ?? null,
                ]);
            } else {
                Log::error('WhatsApp message failed', [
                    'customer_id' => $this->customer->id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                // Throw exception to trigger retry
                throw new \Exception($result['error'] ?? 'Failed to send WhatsApp message');
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp job failed', [
                'customer_id' => $this->customer->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('WhatsApp job permanently failed', [
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // TODO: Notify admin about failure
        // TODO: Update customer notification status
    }
}
