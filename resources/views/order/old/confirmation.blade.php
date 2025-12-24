<div style="max-width:600px;margin:40px auto;
            background:#ffffff;
            border-radius:12px;
            box-shadow:0 10px 25px rgba(0,0,0,0.1);
            padding:30px;
            font-family:Arial, sans-serif">

    <!-- Success Icon -->
    <div style="text-align:center;font-size:48px;color:#16a34a;">
        🎉
    </div>

    <!-- Title -->
    <h2 style="text-align:center;color:#16a34a;margin-bottom:8px;">
        Order Confirmed
    </h2>

    <p style="text-align:center;color:#6b7280;margin-bottom:24px;">
        Thank you for placing your order with us. Our team will contact you shortly to assist you further.
    </p>

    <!-- Order ID -->
    <div style="background:#ecfdf5;
                border:1px dashed #10b981;
                padding:16px;
                border-radius:8px;
                text-align:center;
                margin-bottom:24px;">
        <p style="margin:5;color:#065f46;font-size:15px;">
            Order ID
        </p>
        <p style="margin:0;font-size:22px;font-weight:bold;color:#064e3b;">
            {{ 56789 }}
        </p>
    </div>

    <!-- Customer Details -->
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="padding:8px;color:#6b7280;">Name</td>
            <td style="padding:8px;font-weight:600;">
                {{ $data['customer_name'] }}
            </td>
        </tr>
        <tr>
            <td style="padding:8px;color:#6b7280;">WhatsApp</td>
            <td style="padding:8px;font-weight:600;">
                {{ $data['whatsapp'] }}
            </td>
        </tr>
        <tr>
            <td style="padding:8px;color:#6b7280;">Email</td>
            <td style="padding:8px;font-weight:600;">
                {{ $data['email'] }}
            </td>
        </tr>
        <tr>
            <td style="padding:8px;color:#6b7280;">Address</td>
            <td style="padding:8px;font-weight:600;">
                {{ $data['address'] }}
            </td>
        </tr>
    </table>

    <!-- Total -->
    <div style="border-top:1px solid #e5e7eb;margin-top:20px;padding-top:16px;">
        <p style="display:flex;justify-content:space-between;
                  font-size:18px;font-weight:bold;">
            <span>Total Amount</span>
            <span style="color:#16a34a;">
                ₹{{ number_format($data['grand_total'], 2) }}
            </span>
        </p>
    </div>

</div>