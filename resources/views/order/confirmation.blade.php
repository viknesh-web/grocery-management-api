@vite(['resources/css/pages/confirmation/index.css'])
<div style="max-width:600px;margin:80px auto;background:#ffffff;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.1);padding:30px;">
    <img src="{{ asset('assets/images/logo-xion.png') }}" alt="Xion Logo" style="max-width:150px;margin:auto;display:block;">   
    <h2 style="text-align:center;color:#16a34a;margin-bottom:8px;font-family:'Poppins-Medium';margin-top:30px;">Order Confirmed 🎉</h2>
    <p style="text-align:center;color:#6b7280;margin-bottom:24px;font-family:'Roboto-Regular';">Thank you for placing your order with us. Our team will contact you shortly to assist you further.</p>
    <div style="background:#ecfdf5;border:1px dashed #10b981;padding:16px;border-radius:8px;text-align:center;margin-bottom:24px;">
        <p style="margin:5;color:#065f46;font-size:15px; font-family:'Poppins-Medium';">Order ID</p>
        <p style="margin:0;font-size:22px;font-weight:bold;color:#064e3b;">{{ 56789 }}</p>
    </div>
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="font-weight:500;padding:8px;font-family:'Poppins-Medium';">Name</td>
            <td style="padding:8px;font-family:'Roboto-Regular';">{{ $data['customer_name'] }}</td>
        </tr>
        <tr>
            <td style="font-weight:500;padding:8px;font-family:'Poppins-Medium';">WhatsApp</td>
            <td style="padding:8px;font-family:'Roboto-Regular';">{{ $data['whatsapp'] }}</td>
        </tr>
        <tr>
            <td style="font-weight:500;padding:8px;font-family:'Poppins-Medium';">Email</td>
            <td style="padding:8px;font-family:'Roboto-Regular';">{{ $data['email'] }}</td>
        </tr>
        <tr>
            <td style="font-weight:500;padding:8px;font-family:'Roboto-Medium';">Address</td>
            <td style="padding:8px;font-family:'Poppins-Regular';">{{ $data['address'] }}</td>
        </tr>
    </table>
    <div style="border-top:1px solid #e5e7eb;margin-top:20px;padding-top:16px;">
        <p style="display:flex;justify-content:space-between;font-size:18px;font-weight:bold;">
            <span style=" font-family:'Poppins-Medium';">Total Amount</span>
            <span style="color:#16a34a;font-family:'Roboto-Regular';">₹{{ number_format($data['grand_total'], 2) }}
            </span>
        </p>
    </div>
    <div class="confirmation-submit">
        <a href="{{ route('order.form') }}" class=""> Order Again </a>
    </div>    
</div>
