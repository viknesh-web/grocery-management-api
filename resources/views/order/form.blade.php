<!DOCTYPE html>
<html>
<head>
    <title>Order Form</title>
    <script>
        window.UNIT_CONVERSIONS = @json($unitConversions ?? []);
        window.IS_ADMIN = @json($isAdmin ?? false);
        window.CUSTOMERS = @json($customers ?? []);
        window.PRODUCT_MIN_QTY = @json($productMinQuantities ?? []);
    </script>
  
</head>
<body>
    @vite([
        'resources/js/pages/orderform/index.js',
        'resources/css/pages/orderform/index.css'
    ])
    <div class="order-wrapper">        
        @if($isAdmin)
        <!-- Customer Selection Section (Admin Only) -->
        <div class="customer-selection-section">
            <h3>Select Customer</h3>
            <div class="customer-select-wrapper">
                <div style="flex: 1;">
                    <select id="customerSelectDropdown" name="selected_customer_id">
                        <option value=""> Create order for new customer </option>
                        @foreach($customers as $customer)
                        <option 
                            value="{{ $customer['id'] }}"
                            data-name="{{ $customer['name'] }}"
                            data-phone="{{ $customer['phone'] }}"
                            data-address="{{ $customer['address'] }}"
                        >
                            {{ $customer['name'] }} ({{ $customer['phone'] }})
                        </option>
                        @endforeach
                    </select>                   
                </div>
                
                <div id="customerInfoDisplay" class="customer-info-display">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h4 style="margin: 0; font-size: 14px; color: #1e293b;">Selected Customer</h4>
                        <button type="button" class="clear-customer-btn" onclick="clearCustomerSelection()">Clear</button>
                    </div>
                    <div class="customer-info-grid">
                        <div class="customer-info-item">
                            <span class="customer-info-label">Name</span>
                            <span class="customer-info-value" id="displayName">—</span>
                        </div>
                        <div class="customer-info-item">
                            <span class="customer-info-label">Phone</span>
                            <span class="customer-info-value" id="displayPhone">—</span>
                        </div>
                        
                        <div class="customer-info-item">
                            <span class="customer-info-label">Address</span>
                            <span class="customer-info-value" id="displayAddress">—</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
        
        <!-- Header with Logo -->
        <div class="" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <div class="header" style="text-align: left ; margin-left: 20px;">
                @php
                $logoPaths = [
                    public_path('assets/images/logo-xion.png'),
                    storage_path('app/public/images/logo-xion.png'),
                ];
                $logoBase64 = null;
                foreach ($logoPaths as $path) {
                    if (file_exists($path)) {
                        $data = base64_encode(file_get_contents($path));
                        $logoBase64 = 'data:image/png;base64,' . $data;
                        break;
                    }
                }
                @endphp

                @if($logoBase64)
                <img src="{{ $logoBase64 }}" style="height:65px;">
                @else
                <strong>XION GROCERY</strong>
                @endif
            </div>
            
            <!-- Category Filter -->
            <div class="filter-bar">
                <select id="categoryFilter">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div id="minQtyErrorSummary">
            <h4>Minimum Quantity Not Met</h4>
            <ul id="minQtyErrorList"></ul>
        </div>
        
        <!-- Order Form -->
        <form method="POST" action="{{ route('order.pdf') }}" id="orderForm">
            @csrf
            
            <!-- Hidden fields for admin context -->
            @if($isAdmin)
                <input type="hidden" name="is_admin" value="1">
                <input type="hidden" name="admin_user_id" value="{{ $adminUser?->id ?? '' }}">
                <input type="hidden" name="selected_customer_id" id="hiddenCustomerId" value="">
            @endif
            
            <!-- Product Tables -->
            <div class="tables-wrapper">
                <!-- LEFT TABLE -->
                <div class="table-box">
                    <div class="table-scroll">
                        <table>
                            <colgroup>
                                <col class="img">
                                <col class="name">
                                <col class="code">
                                <col class="price">
                                <col class="qty">
                                <col class="total">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Item Code</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="leftTable" class="productTable"></tbody>
                        </table>
                    </div>
                </div>
                
                <!-- RIGHT TABLE -->
                <div class="table-box">
                    <div class="table-scroll">
                        <table>
                            <colgroup>
                                <col class="img">
                                <col class="name">
                                <col class="code">
                                <col class="price">
                                <col class="qty">
                                <col class="total">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Item Code</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="rightTable" class="productTable"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div id="noProductsMsg" style="display:none;text-align:center;padding:40px;font-size:16px;color:#6b7280;font-weight:500;">
                No products found.
            </div>
            <!-- GRAND TOTAL -->
            <table class="grand-total">
                <tfoot>
                    <tr class="grand-total">
                        <td colspan="5" align="right">Grand Total</td>
                        <td><img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12"> <span id="grandTotal">0.00</span></td>
                    </tr>
                </tfoot>
            </table>
            
            <div id="qtyError" style="display:none; color:#dc2626; margin-bottom:10px; font-weight:600;">
                Please select at least one product quantity before reviewing the order.
            </div>

            <button type="submit" formaction="{{ route('order.review') }}" class="submit" id="reviewBtn">
                Review Order
            </button>
        </form>
    </div>
    
    <!-- Hidden product data -->
    <table style="display:none">
        <tbody id="allProducts">
            @foreach($products as $product)
            @php $price = $product->selling_price ?? $product->regular_price; @endphp
            @php 
                $hasMinQty = $product->hasMinimumQuantity();
                $minQty = $hasMinQty ? $product->min_quantity : null;
            @endphp
            <tr data-category="{{ $product->category_id }}" 
                data-product-id="{{ $product->id }}"
                data-min-qty="{{ $minQty }}"
                data-product-name="{{ $product->name }}"
                data-stock-unit="{{ $product->stock_unit }}">
                <td class="product-image" data-label="Image">
                    <img src="{{ $product->image_url }}">
                </td>
                <td class="order-product-name" data-label="Name">
                    {{ $product->name }}
                    @if($hasMinQty)
                    <span class="min-qty-badge" title="Minimum order quantity">
                        Min: {{ $product->getMinimumQuantityDisplay() }}
                    </span>
                    @endif
                </td>
                
                <td class="item-code"  data-label="Item Code">{{ $product->item_code }}</td>
               
                <td data-label="Price">
                    @if(!empty($product->selling_price) && $product->selling_price < $product->regular_price)
                        <div class="original-price">
                            <img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="10">
                            {{ number_format($product->regular_price, 2) }}
                        </div>
                        <div class="selling-price">
                            <img src="{{ asset('assets/images/Dirham-Symbol.png') }}" width="10">
                            {{ number_format($product->selling_price, 2) }} / {{ $product->stock_unit }}
                        </div>
                        <input type="hidden" class="price-value" value="{{ $product->selling_price }}">
                    @else
                        <div class="selling-price">
                            <img src="{{ asset('assets/images/Dirham-Symbol.png') }}" width="10">
                            {{ number_format($product->regular_price, 2) }} / {{ $product->stock_unit }}
                        </div>
                        <input type="hidden" class="price-value" value="{{ $product->regular_price }}">
                    @endif
                </td>
                <td data-label="Quantity">
                    <div style="display: flex; gap: 5px; align-items: center;justify-content: center;" class="order-qty-box">
                        <input type="number" 
                            step="0.1"
                            min="0" 
                            class="qty"
                            name="products[{{ $product->id }}][qty]"
                            data-product-id="{{ $product->id }}"
                            value="{{ $selectedQty[$product->id]['qty'] ?? '' }}">
                        
                        <select name="products[{{ $product->id }}][unit]" 
                                class="unit-select"
                                data-base-unit="{{ $product->stock_unit }}"
                                data-product-id="{{ $product->id }}"
                                style="padding: 6px;">
                            @foreach($product->getAvailableUnits() as $unit)
                                <option value="{{ $unit }}" 
                                    {{ ($selectedQty[$product->id]['unit'] ?? $product->stock_unit) === $unit ? 'selected' : '' }}>
                                    {{ $unit }}
                                </option>
                            @endforeach
                        </select>
                    </div>                  
                </td>
                <td data-label="Total">
                    <img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12">
                    <span class="row-total">0.00</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <script>
    // Customer selection functionality (Admin only)
    if (window.IS_ADMIN) {
        const customerSelect = document.getElementById('customerSelectDropdown');
        const customerInfoDisplay = document.getElementById('customerInfoDisplay');
        const hiddenCustomerId = document.getElementById('hiddenCustomerId');
        
        // Update customer info display when selection changes
        customerSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const customerId = selectedOption.value;
            
            if (customerId) {
                // Show customer info
                document.getElementById('displayName').textContent = selectedOption.dataset.name || '—';
                document.getElementById('displayPhone').textContent = selectedOption.dataset.phone || '—';
                document.getElementById('displayAddress').textContent = selectedOption.dataset.address || '—';
                
                customerInfoDisplay.classList.add('active');
                hiddenCustomerId.value = customerId;
                
                console.log('Customer selected:', customerId);
            } else {
                // Hide customer info
                customerInfoDisplay.classList.remove('active');
                hiddenCustomerId.value = '';
                
                console.log('No customer selected');
            }
        });
    }
    
    // Clear customer selection
    function clearCustomerSelection() {
        const customerSelect = document.getElementById('customerSelectDropdown');
        const customerInfoDisplay = document.getElementById('customerInfoDisplay');
        const hiddenCustomerId = document.getElementById('hiddenCustomerId');
        
        customerSelect.value = '';
        customerInfoDisplay.classList.remove('active');
        hiddenCustomerId.value = '';
        
        console.log('Customer selection cleared');
    }
    </script>
    
    @vite(['resources/js/pages/orderform/index.js'])
</body>
</html>