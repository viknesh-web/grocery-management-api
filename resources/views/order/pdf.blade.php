<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {font-family: Poppins;font-size: 16x;}
        table {width: 100%;border-collapse: collapse;}
        th,td {border: 1px solid #ccc;padding: 6px;text-align: center;font-size: 10px;}
        th {background: #2C3E50;color: #fff;}
        img {max-width: 70px;max-height: 70px;}
        .price-old,.old-price {text-decoration: line-through;color: #888;font-size: 9px;}
        .price-new,.new-price {font-weight: bold;color: #0a8a0a;}
        .grand-total-price {color: #7F8C8D}
    </style>
</head>
<body>
    <table width="100%" style="border:none;margin-bottom:10px;">
        <tr>
            <td style="border:none;text-align:left;vertical-align:middle;">
                <h2 style="margin:0;">Order Details</h2>
                <div style="font-size:10px;">
                    Generated on: {{ now()->format('d M Y, h:i A') }}
                </div>
            </td>

                <td style="border:none;text-align:right;vertical-align:middle;">
                <img src="{{ public_path('assets/images/logo-xion.png') }}" width="120">
            </td>
        </tr>
    </table>
    <hr style="margin: 8px 0 12px;">
    <table>
        <thead>
            <tr>
                <th>S.No</th>
                <th>Name</th>
                <th>Image</th>
                <th>Code</th>
                <th>Price</th>
                <th>Qty</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
    
            @php $grandTotal = 0; @endphp
            @foreach($products as $item)
            <?php echo $item; ?>
            @php
            $price = ($item->selling_price > 0 && $item->selling_price < $item->original_price)
                ? $item->selling_price
                : $item->original_price;
                $rowTotal = $price * $item->qty;
                $grandTotal += $rowTotal;
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->image_url }}</td>
                    <td><img src="{{ $item->image_url }}"></td>
                    <td>{{ $item->item_code }}</td>
                    <td>
                        @if($item->selling_price > 0 && $item->selling_price < $item->original_price)
                            <div class="old-price"><img src="file://{{ public_path('assets/images/Dirham-Symbol-grey.png') }}" width="10"> {{ number_format($item->original_price,2) }} / {{ $item->stock_unit }}</div>
                            <div class="new-price"><img src="file://{{ public_path('assets/images/Dirham-Symbol.png') }}" width="10"> {{ number_format($item->selling_price,2) }} / {{ $item->stock_unit }}</div>
                            @else
                            <div class="new-price"><img src="file://{{ public_path('assets/images/Dirham-Symbol.png') }}" width="10"> {{ number_format($item->original_price,2) }} / {{ $item->stock_unit }}</div>
                        @endif
                    </td>
                    <td>{{ $item->qty }}</td>
                    <td><img src="file://{{ public_path('assets/images/Dirham-Symbol-grey.png') }}" width="10"> {{ number_format($rowTotal,2) }}</td>
                </tr>
                @endforeach
        </tbody>
        <tfoot>
            <tr class="grand-total">
                <td colspan="6" align="right"><strong>Grand Total</strong></td>
                <td class="grand-total-price"><strong><img src="file://{{ public_path('assets/images/Dirham-Symbol-grey.png') }}" width="10"> {{ number_format($grandTotal,2) }}</strong></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>