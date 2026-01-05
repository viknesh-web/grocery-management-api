<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Price List - {{ $date }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .header p {
            font-size: 14px;
            color: #666;
        }
        .category-section {
            margin-bottom: 20px;
        }
        .category-title {
            font-size: 18px;
            font-weight: bold;
            color: #34495e;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        thead {
            background-color: #34495e;
            color: white;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
        }
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .price {
            font-weight: bold;
            color: #27ae60;
        }
        .original-price {
            color: #e74c3c;
            text-decoration: line-through;
            margin-right: 5px;
        }
        .discount-info {
            font-size: 10px;
            color: #888;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        .no-image {
            width: 50px;
            height: 50px;
            background-color: #ecf0f1;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Daily Grocery Price List</h1>
        <p>Date: {{ $date }} | Time: {{ $time }}</p>
    </div>

    @forelse($groupedProducts as $categoryName => $products)
        <div class="category-section">
            <h2 class="category-title">{{ $categoryName }}</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 10%;">Image</th>
                        <th style="width: 20%;">Product Name</th>
                        <th style="width: 15%;">Item Code</th>
                        <th style="width: 15%;">Price</th>
                        <th style="width: 15%;">Stock</th>
                        <th style="width: 10%;">Unit</th>
                    </tr>
                </thead>
                <?php echo $products;?>
                <tbody>
                    @foreach($products as $index => $product)
                    {{ $product }}
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            @if($product->image)
                                <img src="{{ $product->image}}" class="product-image" alt="{{ $product->name }}" >
                            @else
                                <div class="no-image">No Image</div>
                            @endif
                        </td>
                        <td><strong>{{ $product->name }}</strong></td>
                        <td>{{ $product->item_code }}</td>
                        <td>
                            @if($product->has_discount)
                                <span class="original-price">₹{{ number_format($product->regular_price, 2) }}</span>
                                <span class="price">₹{{ number_format($product->selling_price, 2) }}</span>
                                <div class="discount-info">
                                    @if($product->discount_type === 'percentage')
                                        ({{ number_format($product->discount_value, 0) }}% off)
                                    @elseif($product->discount_type === 'fixed')
                                        (₹{{ number_format($product->discount_value, 2) }} off)
                                    @endif
                                </div>
                            @else
                                <span class="price">₹{{ number_format($product->regular_price, 2) }}</span>
                            @endif
                        </td>
                        <td>{{ number_format($product->stock_quantity, 2) }}</td>
                        <td>{{ strtoupper($product->stock_unit) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p style="text-align: center; padding: 20px;">No products available for the price list.</p>
    @endforelse

    <div class="footer">
        <p>Generated by Grocery Management System | For any queries, please contact us</p>
    </div>
</body>
</html>


