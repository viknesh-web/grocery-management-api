<!DOCTYPE html>
<html>

<head>
    <title>Order Form</title>
    <style>
        /* ===== WRAPPER ===== */
        .order-wrapper {
            width: 85%;
            margin: auto;
            background-image: url('{{ asset("/images/table-bg.png") }}');
            background-repeat: no-repeat;
            background-position: top center;
            background-size: cover;
            border: 1px solid #ddd;
            padding-top: 50px;
        }

        /* FILTER */
        .filter-bar {
            text-align: right;
            margin-bottom: 15px;
            font-size: 14px;
        }

        /* TWO TABLES */
        .tables-wrapper {
            display: flex;
            gap: 20px;
            padding: 0 15px;
        }

        /* TABLE BOX */
        .table-box {
            width: 50%;
            background: #fff;
            border: 1px solid #ddd;
        }

        /* FIXED HEADER */
        .table-box thead th {
            position: sticky;
            top: 0;
            font-size: 16px;
            background: #2C3E50;
            color: #fff;
            z-index: 10;
            padding: 15px;
        }

        /* SCROLL BODY */


        /* TABLE BASE */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            text-align: center;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 6px;
            font-size: 14px;
        }

        /* COLUMN WIDTH LOCK */
        col.img {
            width: 80px;
        }

        col.name {
            width: 120px;
        }

        col.code {
            width: 80px;
        }

        col.price {
            width: 100px;
        }

        col.qty {
            width: 80px;
        }

        col.total {
            width: 80px;
        }

        /* IMAGE */
        .product-image img {
            width: 100%;
            height: 60px;
            object-fit: cover;
            display: block;
        }

        /* PRICE */
        .original-price {
            font-size: 12px;
            color: #7F8C8D;
            text-decoration: line-through;
        }

        .selling-price {
            font-weight: bold;
            color: #3DA222;
        }

        /* INPUT */
        .qty {
            width: 55px;
            text-align: center;
        }

        /* GRAND TOTAL */
        .grand-total td {
            font-weight: bold;
            background: #fafafa;
            font-size: 18px;
            padding: 10px;
            color: #7F8C8D;
        }

        #categoryFilter {
            font-size: 14px;
            color: #fff;
            padding: 8px 18px;
            border-radius: 8px;
            border: 1px solid #3DA222;
            background: #3DA222;
            cursor: pointer;
            margin-right: 20px;
        }

        /* BUTTON */
        .submit {
            font-size: 14px;
            color: #fff;
            padding: 8px 18px;
            border-radius: 8px;
            border: 1px solid #3DA222;
            background: #3DA222;
            cursor: pointer;
            display: block;
            margin: 20px auto 30px;
        }
    </style>

</head>

<body>
    <div class="order-wrapper">
        <div class="header" style="text-align: center ; margin-left: 20px;">
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

        @php
        $chunks = $products->chunk(ceil($products->count() / 2));
        @endphp

        <form method="POST" action="{{ route('order.pdf') }}">
            @csrf

            <div class="tables-wrapper">

                @foreach($chunks as $sideProducts)

                <div class="table-box">

                    <!-- HEADER -->
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
                                <th>Selling Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                    </table>

                    <!-- BODY -->
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
                            <tbody class="productTable">
                                @foreach($sideProducts as $product)
                                @php $price = $product->selling_price ?? $product->original_price; @endphp
                                <tr data-category="{{ $product->category_id }}">
                                    <td class="product-image">
                                        <img src="{{ $product->image_url ?? asset('storage/'.$product->image) }}">
                                    </td>
                                    <td>{{ $product->name }}</td>
                                    <td>{{ $product->item_code }}</td>
                                    <td>
                                        @if(!empty($product->selling_price) && $product->selling_price < $product->original_price)
                                            <div class="original-price">
                                                <img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="10"> {{ number_format($product->original_price, 2) }}
                                            </div>
                                            <div class="selling-price">
                                                <img src="{{ asset('assets/images/Dirham-Symbol.png') }}" width="10"> {{ number_format($product->selling_price, 2) }} / {{ $product->stock_unit }}
                                            </div>
                                            <input type="hidden" class="price-value" value="{{ $product->selling_price }}">
                                            @else
                                            <div class="selling-price">
                                                <img src="{{ asset('assets/images/Dirham-Symbol.png') }}" width="10"> {{ number_format($product->original_price, 2) }} / {{ $product->stock_unit }}
                                            </div>
                                            <input type="hidden" class="price-value" value="{{ $product->original_price }}">
                                            @endif
                                    </td>
                                    <td>
                                        <input type="number" min="0" class="qty"
                                            name="products[{{ $product->id }}][qty]" value="{{ $selectedQty[$product->id]['qty'] ?? '' }}">

                                        <input type="hidden"
                                            name="products[{{ $product->id }}][name]"
                                            value="{{ $product->name }}">
                                    </td>
                                    <td><img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12"> <span class="row-total">0.00</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- GRAND TOTAL -->
            <table style="margin-top:10px;">
                <tfoot>
                    <tr class="grand-total">
                        <td colspan="5" align="right">Grand Total</td>
                        <td><img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12"> <span id="grandTotal">0.00</span></td>
                    </tr>
                </tfoot>
            </table>
            <button type="submit" formaction="{{ route('order.review') }}" class="submit">
                Review Order
            </button>
        </form>
    </div>
    <script>
        function calculateTotals() {
            let grand = 0;

            document.querySelectorAll('.productTable tr').forEach(row => {
                const price = parseFloat(row.querySelector('.price-value').value || 0);
                const qty = parseInt(row.querySelector('.qty').value || 0);
                const total = price * qty;

                row.querySelector('.row-total').textContent = total.toFixed(2);
                grand += total;
            });

            document.getElementById('grandTotal').textContent = grand.toFixed(2);
        }
        document.querySelectorAll('.qty').forEach(q => {
            q.addEventListener('input', calculateTotals);
        });
        document.getElementById('categoryFilter').addEventListener('change', function() {
            const val = this.value;
            document.querySelectorAll('.productTable tr').forEach(row => {
                row.style.display = (!val || row.dataset.category === val) ? '' : 'none';
            });
        });
    </script>
</body>
</html>