<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Order - {{ $orderNumber }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
        }
        .order-info {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #4CAF50;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            font-weight: bold;
            color: #555;
        }
        .info-value {
            color: #333;
        }
        .totals {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 8px 0;
        }
        .total-row.grand-total {
            font-size: 1.2em;
            font-weight: bold;
            border-top: 2px solid #4CAF50;
            padding-top: 12px;
            margin-top: 12px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            font-size: 0.9em;
            color: #777;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .pdf-notice {
            background-color: #e8f5e9;
            border: 2px dashed #4CAF50;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            text-align: center;
        }
        .pdf-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .item-summary {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #FF9800;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1> New Order Received!</h1>
        <p>Order #{{ $orderNumber }}</p>
    </div>

    <div class="content">
        <p>Hello Admin,</p>
        <p>A new order has been placed. Please review the details below:</p>

        <div class="order-info">
            <h3>Order Information</h3>
            <div class="info-row">
                <span class="info-label">Order Number:</span>
                <span class="info-value">{{ $orderNumber }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Order Date:</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($orderDate)->format('M d, Y h:i A') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value">
                    <span class="status-badge status-{{ strtolower($status) }}">
                        {{ ucfirst($status) }}
                    </span>
                </span>
            </div>
        </div>

        <div class="order-info">
            <h3>Customer Details</h3>
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span class="info-value">{{ $customer->name ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone:</span>
                <span class="info-value">{{ $customer->whatsapp_number ?? 'N/A' }}</span>
            </div>
            @if($customer && $customer->email)
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value">{{ $customer->email }}</span>
            </div>
            @endif
            @if($customer && $customer->address)
            <div class="info-row">
                <span class="info-label">Address:</span>
                <span class="info-value">{{ $customer->address }}</span>
            </div>
            @endif
        </div>

        <div class="item-summary">
            <h3>Order Summary</h3>
            <div class="info-row">
                <span class="info-label">Total Items:</span>
                <span class="info-value">{{ $items->count() }} item(s)</span>
            </div>
        </div>

        <div class="pdf-notice">
            <div class="pdf-icon"></div>
            <h3 style="margin: 10px 0; color: #4CAF50;">Order Details Attached</h3>
            <p style="color: #666; margin: 10px 0;">
                Complete order details with all items are available in the attached PDF file.
            </p>
            <p style="color: #999; font-size: 0.9em;">
                <strong>Order_{{ $orderNumber }}.pdf</strong>
            </p>
        </div>

        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>AED {{ number_format($subtotal, 2) }}</span>
            </div>
            @if($discountAmount > 0)
            <div class="total-row" style="color: #4CAF50;">
                <span>Saved:</span>
                <span>AED {{ number_format($discountAmount, 2) }}</span>
            </div>
            @endif
            <div class="total-row grand-total">
                <span>Grand Total:</span>
                <span>AED {{ number_format($total, 2) }}</span>
            </div>
        </div>

        <div style="text-align: center;">
            <a href="{{ config('app.url') }}/admin/orders/{{ $order->id }}" class="btn">
                View Order Details
            </a>
        </div>

        <p style="margin-top: 30px; color: #777; font-size: 0.9em;">
            This is an automated notification. Please log in to your admin panel to manage this order.
        </p>
    </div>

    <div class="footer">
        <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        <p>This email was sent to notify you about a new order.</p>
    </div>
</body>
</html>