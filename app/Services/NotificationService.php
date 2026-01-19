<?php

namespace App\Services;

use App\Mail\OrderConfirmedMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notification Service - Admin Only
 * 
 * Handles sending order confirmation emails to admin
 */
class NotificationService
{
    /**
     * Send order confirmation email to admin
     *
     * @param Order $order
     * @return bool
     */
    public function sendOrderConfirmedToAdmin(Order $order): bool
    {
        try {
            $adminEmail = config('mail.admin_email', env('ADMIN_EMAIL'));
            
            if (empty($adminEmail)) {
                Log::warning('Admin email not configured, skipping order notification', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);
                return false;
            }
            
            // Send email to admin
            Mail::to($adminEmail)->send(new OrderConfirmedMail($order));
            
            Log::info('Order confirmation email sent to admin', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'admin_email' => $adminEmail,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email to admin', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Send order confirmation email to admin using queue (async)
     * 
     *
     * @param Order $order
     * @return bool
     */
    public function sendOrderConfirmedToAdminAsync(Order $order): bool
    {
        try {
            $adminEmail = config('mail.admin_email', env('ADMIN_EMAIL'));
            
            if (empty($adminEmail)) {
                Log::warning('Admin email not configured, skipping order notification', [
                    'order_id' => $order->id,
                ]);
                return false;
            }
            
            Mail::to($adminEmail)->queue(new OrderConfirmedMail($order));
            
            Log::info('Order confirmation email queued for admin', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to queue order confirmation email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
}