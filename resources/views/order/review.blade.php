<!DOCTYPE html>
<html>
<head>
    <title>Review Order</title>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/pages/review/index.css'])
    <script>
        window.IS_ADMIN = @json($is_admin ?? false);
        window.ADMIN_TOKEN = @json($admin_token ?? '');
        window.SELECTED_CUSTOMER = @json($selected_customer ?? null);
    </script>
    <style>
        .selected-customer-banner {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .selected-customer-banner h3 {
            margin: 0 0 15px 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .customer-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .customer-detail-item {
            display: flex;
            flex-direction: column;
        }
        .customer-detail-label {
            font-size: 11px;
            font-weight: 600;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .customer-detail-value {
            font-size: 15px;
            font-weight: 500;
        }
        .admin-mode-badge {
            display: inline-block;
            background: rgba(255,255,255,0.3);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div>
            <div class="" style="text-align: left; margin-left: 20px;">
                <img src="{{ asset('assets/images/logo-xion.png') }}" alt="Xion Logo" style="max-width:150px;margin:auto;display:block;">
            </div>
            <h2>Review Your Order 
                @if($is_admin)
                <span class="admin-mode-badge">ADMIN MODE</span>
                @endif
            </h2>
        </div>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Image</th>
                    <th>Product</th>
                    <th>Code</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @php $grandTotal = 0; @endphp
                @foreach($products as $product)
                    @php
                        $qty = $product->cart_qty ?? $product->qty ?? 0; 
                        $unit = $product->cart_unit ?? $product->stock_unit;

                        $price = ($product->selling_price > 0 && $product->selling_price < $product->regular_price)
                            ? $product->selling_price
                            : $product->regular_price;

                        $rowTotal = $product->cart_subtotal ?? ($price * $qty);  
                        $grandTotal += $rowTotal;
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            <img src="{{ $product->image_url }}" class="product-img" alt="{{ $product->name }}">
                        </td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->item_code }}</td>
                        <td>
                            @if($product->selling_price > 0 && $product->selling_price < $product->regular_price)
                                <div class="old-price">
                                    <img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12"> 
                                    {{ number_format($product->regular_price, 2) }}
                                </div>
                                <div class="new-price">
                                    <img src="{{ asset('assets/images/Dirham-Symbol.png') }}" width="12"> 
                                    {{ number_format($product->selling_price, 2) }} / {{ $product->stock_unit }}
                                </div>
                            @else
                                <div class="new-price">
                                    <img src="{{ asset('assets/images/Dirham-Symbol.png') }}" width="12"> 
                                    {{ number_format($product->regular_price, 2) }} / {{ $product->stock_unit }}
                                </div>
                            @endif
                        </td>
                        <td>
                             <span class="qty-badge">{{ $qty }} {{ $unit }}</span>
                        </td>
                        <td class="total-cell">
                            <img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12"> 
                            {{ number_format($rowTotal, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
        <!-- GRAND TOTAL -->
        <div class="grand-total">
            Grand Total: 
            <img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12" height="12" style="padding: 8px 5px 0px 10px;"> 
            {{ number_format($grandTotal, 2) }}
        </div>
        
        <!-- Actions -->
        <div class="actions">
            <a href="{{ route('order.form', ['from' => 'review']) }}" class="btn btn-back">
                ← Back to Products
            </a>
            
            <form method="POST" action="{{ route('order.pdf') }}" style="display:inline;">
                @csrf
                @if($is_admin)
                    <input type="hidden" name="is_admin" value="1">
                    <input type="hidden" name="admin_user_id" value="{{ $admin_user_id ?? '' }}">
                @endif
                <button type="submit" class="btn btn-download">
                    Preview Order
                </button>
            </form>
            
            <button type="button" class="btn btn-download" onclick="openOrderModal()">
                Place Order
            </button>
        </div>
    </div>
    
    <!-- ORDER MODAL -->
    <div id="orderModal" class="modal-overlay" data-submit-url="/order/confirmation">
        <div class="modal-box">
            <h3>
                @if($is_admin && $selected_customer)
                    Confirm Customer Details
                @else
                    Customer Details
                @endif
            </h3>
            <p class="modal-subtitle">
                @if($is_admin && $selected_customer)
                    Review the customer information below
                @else
                    Please fill in your information to complete the order
                @endif
            </p>
            
            <form id="orderConfirmForm">
                <!-- Hidden fields for admin context -->
                @if($is_admin)
                    <input type="hidden" name="is_admin" value="1">
                    <input type="hidden" name="admin_user_id" value="{{ $admin_user_id ?? '' }}">
                    @if($selected_customer)
                        <input type="hidden" name="selected_customer_id" value="{{ $selected_customer->id }}">
                    @endif
                @endif
                
                <div class="form-group">
                    <label for="customer_name">
                        Full Name <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="customer_name"
                        name="customer_name" 
                        oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" 
                        required
                        placeholder="Enter your full name"
                        value="{{ $selected_customer->name ?? '' }}"
                        @if($is_admin && $selected_customer) readonly style="background: #f1f5f9;" @endif
                    >
                </div>
                
                <div class="form-group">
                    <label for="whatsapp">
                        WhatsApp Number <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="whatsapp"
                        name="whatsapp" 
                        oninput="this.value = this.value.replace(/[^0-9+\-\s]/g, '')" 
                        required
                        placeholder="e.g., +971 50 123 4567"
                        value="{{ $selected_customer->whatsapp_number ?? '' }}"
                        @if($is_admin && $selected_customer) readonly style="background: #f1f5f9;" @endif
                    >
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email"
                        name="email" 
                        placeholder="your.email@example.com"
                        value="{{ $selected_customer->email ?? '' }}"
                        @if($is_admin && $selected_customer && $selected_customer->email) readonly style="background: #f1f5f9;" @endif
                    >
                </div>
                
                <div class="form-group" style="position:relative;">
                    <label for="addressInput">
                        Delivery Address <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="addressInput" 
                        name="address" 
                        autocomplete="off" 
                        placeholder="Start typing Dubai address"
                        required
                        value="{{ $selected_customer->address ?? '' }}"
                        @if($is_admin && $selected_customer && $selected_customer->address) readonly style="background: #f1f5f9;" @endif
                    >
                    <ul id="addressSuggestions" class="address-dropdown"></ul>
                </div>
                
                <div id="formErrors" style="display:none;"></div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-back" onclick="closeOrderModal()">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-download" onclick="submitOrderAjax()">
                        @if($is_admin && $selected_customer)
                            Confirm & Submit Order
                        @else
                            Submit Order
                        @endif
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    // Log selected customer data for debugging
    if (window.SELECTED_CUSTOMER) {
        console.log('Selected customer loaded:', window.SELECTED_CUSTOMER);
    }
    
    // Disable address autocomplete for readonly fields
    if (window.IS_ADMIN && window.SELECTED_CUSTOMER) {
        const addressInput = document.getElementById('addressInput');
        if (addressInput && addressInput.readOnly) {
            addressInput.addEventListener('focus', function(e) {
                e.target.blur();
            });
        }
    }
    </script>
    
    @vite(['resources/js/pages/review/index.js'])
</body>
</html>