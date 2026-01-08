
<!DOCTYPE html>
<html>
<head>
    <title>Order Form</title>
    <script>
        window.UNIT_CONVERSIONS = @json($unitConversions ?? []);
    </script>
</head>
<body>
    @vite([
        'resources/js/pages/orderform/index.js',
        'resources/css/pages/orderform/index.css'
    ])
    <div class="order-wrapper">
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
            <!-- FILTER -->
            <div class="filter-bar">
                <select id="categoryFilter">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <form method="POST" action="{{ route('order.pdf') }}">
            @csrf
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
            <div id="noProductsMsg" style="
    display:none;
    text-align:center;
    padding:40px;
    font-size:16px;
    color:#6b7280;
    font-weight:500;
">
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
    <table style="display:none">
        <tbody id="allProducts">
            @foreach($products as $product)
            @php $price = $product->selling_price ?? $product->regular_price; @endphp
            <tr data-category="{{ $product->category_id }}">
                <td class="product-image" data-label="Image">
                    <img src="{{ $product->image_url }}">
                </td>
                <td class="order-product-name" data-label="Name">{{ $product->name }}</td>
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
                            step="0.01"
                            min="0" 
                            class="qty"
                            name="products[{{ $product->id }}][qty]"
                            value="{{ $selectedQty[$product->id]['qty'] ?? '' }}">
                        
                        <select name="products[{{ $product->id }}][unit]" 
                                class="unit-select"
                                data-base-unit="{{ $product->stock_unit }}"
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
    @vite(['resources/js/pages/orderform/index.js'])

</body>

</html>