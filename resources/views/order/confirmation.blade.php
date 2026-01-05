<!DOCTYPE html>
<html>
<head>
    <title>Order Confirmation</title>
    <meta charset="utf-8">
</head>
<body>
    @vite(['resources/css/pages/confirmation/index.css'])
    <div style="max-width:600px;margin:80px auto;background:#ffffff;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.1);padding:30px;">
        <img src="{{ asset('assets/images/logo-xion.png') }}" alt="Xion Logo" style="max-width:150px;margin:auto;display:block;">   
        <h2 style="text-align:center;color:#16a34a;margin-bottom:8px;font-family:'Poppins-Medium';margin-top:30px;">Order Confirmed 🎉</h2>
        <p style="text-align:center;color:#6b7280;margin-bottom:24px;font-family:'Roboto-Regular';">Thank you for placing your order with us. Our team will contact you shortly to assist you further.</p>
        <div style="background:#ecfdf5;border:1px dashed #10b981;padding:16px;border-radius:8px;text-align:center;margin-bottom:24px;">
            <p style="margin:5px 0;color:#065f46;font-size:15px;font-family:'Poppins-Medium';">Order Number</p>
            <p style="margin:0;font-size:22px;font-weight:bold;color:#064e3b;font-family:'Poppins-Bold';">{{ $data['order_number'] ?? 'N/A' }}</p>
        </div>
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="font-weight:500;padding:8px;font-family:'Poppins-Medium';color:#374151;">Name</td>
                <td style="padding:8px;font-family:'Roboto-Regular';color:#1f2937;">{{ $data['customer_name'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="font-weight:500;padding:8px;font-family:'Poppins-Medium';color:#374151;">WhatsApp</td>
                <td style="padding:8px;font-family:'Roboto-Regular';color:#1f2937;">{{ $data['customer_phone'] ?? 'N/A' }}</td>
            </tr>
            @if(isset($data['customer_email']) && $data['customer_email'])
            <tr>
                <td style="font-weight:500;padding:8px;font-family:'Poppins-Medium';color:#374151;">Email</td>
                <td style="padding:8px;font-family:'Roboto-Regular';color:#1f2937;">{{ $data['customer_email'] }}</td>
            </tr>
            @endif
            <tr>
                <td style="font-weight:500;padding:8px;font-family:'Roboto-Medium';color:#374151;">Address</td>
                <td style="padding:8px;font-family:'Poppins-Regular';color:#1f2937;">{{ $data['customer_address'] ?? 'N/A' }}</td>
            </tr>
        </table>
        <div style="border-top:1px solid #e5e7eb;margin-top:20px;padding-top:16px;">
            <p style="display:flex;justify-content:space-between;font-size:18px;font-weight:bold;">
                <span style="font-family:'Poppins-Medium';color:#374151;">Total Amount</span>
                <span style="color:#16a34a;font-family:'Roboto-Regular';">
                    <img src="{{ asset('assets/images/Dirham-Symbol.png') }}" width="14" style="vertical-align:middle;"> 
                    {{ number_format($data['total_amount'] ?? 0, 2) }}
                </span>
            </p>
        </div>
        <div class="confirmation-submit">
            <a href="{{ route('order.form') }}">Order Again</a>
        </div>    
    </div>
</body>
</html>