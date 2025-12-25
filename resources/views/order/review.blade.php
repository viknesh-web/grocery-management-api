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
            <h3>Customer Details</h3>
            <form method="POST" action="{{ route('order.confirmation') }}">
                @csrf
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="customer_name" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" value="{{ old('customer_name') }}" required>
                </div>
                <div class="form-group">
                    <label>WhatsApp Number</label>
                    <input type="text" name="whatsapp" oninput="this.value = this.value.replace(/[^0-9+\-\s]/g, '')" value="{{ old('whatsapp') }}" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}">
                </div>
                <div class="form-group" style="position:relative;">
                    <label>Address (Dubai only)</label>
                    <input type="text" id="addressInput" name="address" autocomplete="off" placeholder="Start typing Dubai address"
                        value="{{ old('address') }}">
                    <ul id="addressSuggestions" class="address-dropdown"></ul>
                </div>
                <!-- Pass Grand Total -->
                <input type="hidden" name="grand_total" value="{{ $grandTotal }}">
                <div class="modal-actions">
                    <button type="button" class="btn btn-back" onclick="closeOrderModal()">Cancel</button>
                    <button type="button" class="btn btn-download" onclick="submitOrderAjax()">Submit Order</button>
                </div>
                <div id="formErrors" class="alert alert-danger"></div>
            </form>
        </div>
    </div>
    @vite(['resources/js/pages/review/index.js'])
</body>
</html>