<!DOCTYPE html>
<html>
<head>
    <title>Review Order</title>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    @vite(['resources/css/pages/review/index.css'])
    <div class="container">
        <div>
            <div class="" style="text-align: left ; margin-left: 20px;">
                <img src="{{  asset('assets/images/logo-xion.png') }}" alt="Xion Logo" style="max-width:150px;margin:auto;display:block;">
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
                $price = ($product->selling_price > 0)
                ? $product->selling_price
                : $product->price;
                $rowTotal = $price * $product->qty;
                $grandTotal += $rowTotal;
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        <img src="{{ $product->image_url }}" class="product-img">
                    </td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->item_code }}</td>
                    <td>
                        @if($product->selling_price > 0 && $product->selling_price < $product->original_price)
                            <div class="old-price"><img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12"> {{ number_format($product->original_price,2) }}</div>
                            <div class="new-price"><img src="{{ asset('assets/images/Dirham-Symbol.png') }}" width="12"> {{ number_format($product->selling_price,2) }}/ {{ $product->stock_unit }}</div>
                            @else
                            <div class="new-price"><img src="{{ asset('assets/images/Dirham-Symbol.png') }}" width="12"> {{ number_format($product->original_price,2) }}/ {{ $product->stock_unit }}</div>
                            @endif
                    </td>
                    <td>
                        <span class="qty-badge">{{ $product->qty }}</span>
                    </td>
                    <td class="total-cell">
                        <img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12"> {{ number_format($rowTotal,2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <!-- GRAND TOTAL -->
        <div class="grand-total">
            Grand Total : <img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12" height="12" style="padding: 8px 5px 0px 10px;"> {{ number_format($grandTotal,2) }}
        </div>
        <!-- ACTIONS -->
        <div class="actions">
            <a href="{{ route('order.form', ['from' => 'review']) }}" class="btn btn-back">
                ← Back to Products
            </a>
            <form method="POST" action="{{ route('order.pdf') }}">
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
    <div id="orderModal" class="modal-overlay" data-submit-url="{{ route('order.confirmation') }}">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title">Customer Details</h3>
                <button type="button" class="modal-close" onclick="closeOrderModal()" aria-label="Close">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('order.confirmation') }}" id="orderForm">
                @csrf

                <div class="modal-body">
                    <div class="form-grid">
                        <!-- Name -->
                        <div class="form-group">
                            <label for="customer_name">
                                Full Name
                                <span class="required">*</span>
                            </label>
                            <input
                                type="text"
                                id="customer_name"
                                name="customer_name"
                                value="{{ old('customer_name') }}"
                                placeholder="John Doe"
                                oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')"
                                required
                            >
                            <div class="field-error" data-error-for="customer_name"></div>
                        </div>

                        <!-- WhatsApp -->
                        <div class="form-group">
                            <label for="whatsapp">
                                WhatsApp Number
                                <span class="required">*</span>
                            </label>
                            <div class="input-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                </svg>
                                <input
                                    type="text"
                                    id="whatsapp"
                                    name="whatsapp"
                                    value="{{ old('whatsapp') }}"
                                    placeholder="+971 5X XXX XXXX"
                                    oninput="this.value = this.value.replace(/[^0-9+\-\s]/g, '')"
                                    required
                                >
                            </div>
                            <div class="field-error" data-error-for="whatsapp"></div>
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label for="email">
                                Email Address
                                <span class="optional">(Optional)</span>
                            </label>
                            <div class="input-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                                    <path d="M22 7L13.03 12.7a2 2 0 01-2.06 0L2 7"/>
                                </svg>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    placeholder="john@example.com"
                                >
                            </div>
                            <div class="field-error" data-error-for="email"></div>
                        </div>

                        <!-- Address -->
                        <div class="form-group full-width">
                            <label for="addressInput">
                                Delivery Address
                                <span class="required">*</span>
                                <span class="label-info">Dubai only</span>
                            </label>
                            <div class="input-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                                    <circle cx="12" cy="10" r="3"/>
                                </svg>
                                <input
                                    type="text"
                                    id="addressInput"
                                    name="address"
                                    autocomplete="off"
                                    placeholder="Start typing your Dubai address..."
                                    value="{{ old('address') }}"
                                    required
                                >
                            </div>
                            <ul id="addressSuggestions" class="address-dropdown"></ul>
                            <div class="field-error" data-error-for="address"></div>
                        </div>
                    </div>

                    <!-- Hidden total -->
                    <input type="hidden" name="grand_total" value="{{ $grandTotal }}">

                    <!-- Form Errors -->
                    <div id="formErrors" class="form-errors"></div>
                </div>

                <!-- Actions -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeOrderModal()">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="submitOrderAjax()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                        Submit Order
                    </button>
                </div>
            </form>
        </div>
    </div>
    @vite(['resources/js/pages/review/index.js'])
</body>
</html>