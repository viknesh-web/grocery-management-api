<!DOCTYPE html>
<html>
<head>
    <title>Review Order</title>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/pages/review/index.css'])
</head>
<body>
    <div class="container">
        <div>
            <div class="" style="text-align: left; margin-left: 20px;">
                <img src="{{ asset('assets/images/logo-xion.png') }}" alt="Xion Logo" style="max-width:150px;margin:auto;display:block;">
            </div>
            <h2>Review Your Order</h2>
        </div>
        
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
                        $price = ($product->selling_price > 0 && $product->selling_price < $product->regular_price)
                            ? $product->selling_price
                            : $product->regular_price;
                        $rowTotal = $price * $product->qty;
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
                            <span class="qty-badge">{{ $product->qty }}</span>
                        </td>
                        <td class="total-cell">
                            <img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12"> 
                            {{ number_format($rowTotal, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        
        <!-- GRAND TOTAL -->
        <div class="grand-total">
            Grand Total: 
            <img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12" height="12" style="padding: 8px 5px 0px 10px;"> 
            {{ number_format($grandTotal, 2) }}
        </div>
        
        <!-- ACTIONS -->
        <div class="actions">
            <a href="{{ route('order.form', ['from' => 'review']) }}" class="btn btn-back">
                ← Back to Products
            </a>
            
            <form method="POST" action="{{ route('order.pdf') }}" style="display:inline;">
                @csrf
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
    <div id="orderModal" class="modal-overlay" data-submit-url="{{ route('order.confirmation.post') }}">
        <div class="modal-box">
            <h3>Customer Details</h3>
            
            <form id="orderConfirmForm">
                @csrf
                
                <div class="form-group">
                    <label for="customer_name">Name <span style="color:#dc2626;">*</span></label>
                    <input 
                        type="text" 
                        id="customer_name"
                        name="customer_name" 
                        oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" 
                        value="{{ old('customer_name') }}" 
                        required
                        placeholder="Enter your full name"
                    >
                </div>
                
                <div class="form-group">
                    <label for="whatsapp">WhatsApp Number <span style="color:#dc2626;">*</span></label>
                    <input 
                        type="text" 
                        id="whatsapp"
                        name="whatsapp" 
                        oninput="this.value = this.value.replace(/[^0-9+\-\s]/g, '')" 
                        value="{{ old('whatsapp') }}" 
                        required
                        placeholder="e.g., +971501234567"
                    >
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input 
                        type="email" 
                        id="email"
                        name="email" 
                        value="{{ old('email') }}"
                        placeholder="your.email@example.com (optional)"
                    >
                </div>
                
                <div class="form-group" style="position:relative;">
                    <label for="addressInput">Address (Dubai only) <span style="color:#dc2626;">*</span></label>
                    <input 
                        type="text" 
                        id="addressInput" 
                        name="address" 
                        autocomplete="off" 
                        placeholder="Start typing Dubai address"
                        value="{{ old('address') }}"
                        required
                    >
                    <ul id="addressSuggestions" class="address-dropdown"></ul>
                </div>
                
                <!-- Pass Grand Total -->
                <input type="hidden" name="grand_total" value="{{ $grandTotal }}">
                
                <div id="formErrors" style="display:none;"></div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-back" onclick="closeOrderModal()">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-download" onclick="submitOrderAjax()">
                        Submit Order
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    @vite(['resources/js/pages/review/index.js'])
</body>
</html>