<?php

namespace App\Jobs;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Don't retry batch job
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?array $customerIds,
        public ?string $message,
        public ?string $pdfUrl = null,
        public ?string $templateId = null,
        public ?array $contentVariables = null,
        public ?string $batchId = null
    ) {
        $this->batchId = $batchId ?? uniqid('batch_', true);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting WhatsApp batch send', [
            'batch_id' => $this->batchId,
            'customer_count' => $this->customerIds ? count($this->customerIds) : 'all',
        ]);

        // Get customers
        if ($this->customerIds === null || empty($this->customerIds)) {
            // Send to all active customers in chunks
            Customer::where('status', 'active')
                ->chunkById(100, function ($customers) {
                    $this->dispatchJobs($customers);
                });
        } else {
            // Send to specific customers in chunks
            Customer::whereIn('id', $this->customerIds)
                ->chunkById(100, function ($customers) {
                    $this->dispatchJobs($customers);
                });
        }

        Log::info('WhatsApp batch jobs dispatched', [
            'batch_id' => $this->batchId,
        ]);
    }

    /**
     * Dispatch individual send jobs
     */
    private function dispatchJobs($customers): void
    {
        foreach ($customers as $customer) {
            SendWhatsAppMessage::dispatch(
                $customer,
                $this->message,
                $this->pdfUrl,
                $this->templateId,
                $this->contentVariables
            )
            ->onQueue('whatsapp') // Use dedicated queue
            ->delay(now()->addSeconds(rand(1, 5))); // Spread out requests
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('WhatsApp batch job failed', [
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage(),
        ]);
    }
}
