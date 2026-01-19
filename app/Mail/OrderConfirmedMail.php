<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\OrderPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Order Confirmed Mail - Uses OrderPdfService
 * 
 * Best option: Uses your existing PDF service for consistency
 */
class OrderConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    private ?string $pdfPath = null;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Order $order,
        private ?OrderPdfService $pdfService = null
    ) {
        // Load necessary relationships
        $this->order->load(['customer', 'items.product.category']);
        
        // Initialize PDF service if not provided
        if (!$this->pdfService) {
            $this->pdfService = app(OrderPdfService::class);
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Order Received - ' . $this->order->order_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.confirmed',
            with: [
                'order' => $this->order,
                'customer' => $this->order->customer,
                'items' => $this->order->items,
                'orderNumber' => $this->order->order_number,
                'orderDate' => $this->order->order_date,
                'total' => $this->order->total,
                'subtotal' => $this->order->subtotal,
                'discountAmount' => $this->order->discount_amount,
                'status' => $this->order->status,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        try {
            // Convert order items to products collection for PDF service
            $products = $this->order->items->map(function ($item) {
                $product = $item->product;
                $product->cart_qty = $item->quantity;
                $product->cart_unit = $item->unit;
                $product->cart_price = $item->unit_price;
                $product->cart_subtotal = $item->subtotal;
                return $product;
            });
            
            // Use your existing OrderPdfService to generate PDF
            $response = $this->pdfService->generate($products);
            
            // Get the PDF content from the response
            $pdfContent = $response->getContent();
            
            // Save to temp location using media disk
            $filename = 'Order_' . $this->order->order_number . '_' . time() . '.pdf';
            $this->pdfPath = 'pdfs/orders/temp/' . $filename;
            
            $disk = Storage::disk('media');
            
            // Ensure directory exists
            if (!$disk->exists('pdfs/orders/temp')) {
                $disk->makeDirectory('pdfs/orders/temp');
            }
            
            // Save PDF
            $disk->put($this->pdfPath, $pdfContent, 'public');
            
            // Get full path for attachment
            $fullPath = $disk->path($this->pdfPath);
            
            Log::info('PDF attachment created using OrderPdfService', [
                'order_id' => $this->order->id,
                'filename' => $filename,
                'path' => $this->pdfPath,
            ]);
            
            return [
                Attachment::fromPath($fullPath)
                    ->as('Order_' . $this->order->order_number . '.pdf')
                    ->withMime('application/pdf'),
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create PDF attachment for email', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty array if PDF generation fails
            return [];
        }
    }
    
    /**
     * Clean up temporary PDF file after email is sent
     */
    public function __destruct()
    {
        if ($this->pdfPath) {
            try {
                $disk = Storage::disk('media');
                
                if ($disk->exists($this->pdfPath)) {
                    $disk->delete($this->pdfPath);
                    
                    Log::debug('Temporary PDF file deleted', [
                        'path' => $this->pdfPath,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to delete temporary PDF', [
                    'path' => $this->pdfPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}