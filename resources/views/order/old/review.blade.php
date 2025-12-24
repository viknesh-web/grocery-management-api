<!DOCTYPE html>
<html>

<head>
    <title>Review Order</title>
    <meta charset="utf-8">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 40px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: #ffffff;
            padding: 25px 30px 35px;
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
        }

        h2 {
            margin-bottom: 20px;
            color: #2C3E50;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #2C3E50;
            color: #fff;
            padding: 12px;
            font-size: 13px;
            text-transform: uppercase;
        }

        tbody td {
            padding: 10px;
            border-bottom: 1px solid #e5e5e5;
            text-align: center;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        .product-img {
            width: 70px;
            height: 50px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .old-price {
            text-decoration: line-through;
            color: #888;
            font-size: 12px;
        }

        .new-price {
            font-weight: bold;
            color: #3DA222;
            font-size: 14px;
        }

        .qty-badge {
            background: #3DA222;
            color: #fff;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
        }

        .total-cell {
            font-weight: bold;
            color: #7F8C8D;
        }

        .grand-total {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            font-size: 18px;
            font-weight: bold;
            color: #7F8C8D;
        }

        .actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .btn {
            padding: 10px 22px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .btn-back {
            background: #ecf0f1;
            color: #2C3E50;
            border: 1px solid #ccc;
        }

        .btn-download {
            background: #3DA222;
            color: #fff;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            z-index: 999;
        }

        .modal-box {
            background: #fff;
            width: 420px;
            padding: 25px;
            border-radius: 8px;
        }

        .modal-box h3 {
            margin-bottom: 15px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        .modal-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
    </style>
</head>

<body>

    <div class="container">

        <h2>Review Your Order</h2>

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
                        <img src="{{ $product->image_url ?? asset('storage/'.$product->image) }}" class="product-img">
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
            Grand Total : <img src="{{ asset('assets/images/Dirham-Symbol-grey.png') }}" width="12" height="12" style="padding: 4px 5px 0px 10px;"> {{ number_format($grandTotal,2) }}
        </div>

        <!-- ACTIONS -->
        <div class="actions">
            <a href="{{ route('order.form', ['from' => 'review']) }}" class="btn btn-back">
                ← Back to Products
            </a>

            <form method="POST" action="{{ route('order.pdf') }}">
                @csrf
                <button type="submit" class="btn btn-download">
                    Download PDF
                </button>
            </form>
            <button type="button" class="btn btn-download" onclick="openOrderModal()">
                Place Order
            </button>

        </div>

    </div>

    <!-- ORDER MODAL -->
    <div id="orderModal" class="modal-overlay">
        <div class="modal-box">
            <h3>Customer Details</h3>


            <form method="POST" action="{{ route('order.confirmation') }}">
                @csrf

                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="customer_name" value="{{ old('customer_name') }}" required>
                </div>

                <div class="form-group">
                    <label>WhatsApp Number</label>
                    <input type="text" name="whatsapp" value="{{ old('whatsapp') }}" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}">
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="3" required>{{ old('address') }}</textarea>
                </div>

                <!-- Pass Grand Total -->
                <input type="hidden" name="grand_total" value="{{ $grandTotal }}">

                <div class="modal-actions">
                    <button type="button" class="btn btn-back" onclick="closeOrderModal()">Cancel</button>
                    <button type="submit" class="btn btn-download">Submit Order</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openOrderModal() {
            document.getElementById('orderModal').style.display = 'flex';
        }

        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
    </script>


</body>

</html>