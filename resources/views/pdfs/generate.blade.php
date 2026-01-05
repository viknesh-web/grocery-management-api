<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

$generatedAt = Carbon::now();

?>

<!DOCTYPE html>
<html>
 
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
    font-family: 'Poppins-Regular';
    src: url('{{ public_path("fonts/Poppins-Regular.ttf") }}') format('truetype');
}
 
@font-face {
    font-family: 'Poppins-Medium';
    src: url('{{ public_path("fonts/Poppins-Medium.ttf") }}') format('truetype');
}
 
@font-face {
    font-family: 'Poppins-Bold';
    src: url('{{ public_path("fonts/Poppins-Bold.ttf") }}') format('truetype');
}
 
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
 
        body {
            background-image:url('{{ public_path("assets/images/table-bg.png") }}');
            background-size: cover;
            font-family: 'Poppins-Regular', sans-serif;
            padding: 20px;
        }
 
        .header-table h2 {
            font-size: 40px;
            color: #353535;
            margin-bottom: 10px;
            font-family: 'Poppins-Medium', sans-serif;
        }
 
        .date-time span {
            display: inline-block;
            margin-right: 20px;
            font-size: 14px;
        }
 
        .content-table thead th {
            background: #2C3E50;
            color: #fff;
            font-family: 'Poppins-Medium', sans-serif;
            padding: 8px;
            text-align: left;
        }
 
        .item-code {
            font-size: 8px;
            color: #404A4B;
            font-family: "Poppins-Regular";
            white-space: wrap;
            padding: 5px;
            white-space: normal;
            word-break: break-all;
        }
 
        .item-name {
            font-size: 10px;
            color: #2C3E50;
            font-family: "Poppins";
            font-weight: 700;
            padding: 5px;
        }
 
        .date-time img {
            vertical-align: middle;
            margin-right: 5px;
        }
 
        .header-table {
            margin-top: 50px;
        }
 
        .content-wrapper {
            margin-top: 20px;
        }
 
        .content-table td {
            border: 1px solid #ddd;
            vertical-align: middle;
            overflow: hidden;
            font-family: 'Poppins-Regular', sans-serif;
 
        }
 
        .content-table tbody tr:nth-child(even) {
            background: #3DA2221A;
        }
 
        .content-table tbody tr:nth-child(odd) {
            background: #fff;
        }
 
        .old-price {
            text-decoration: line-through;
            color: #999;
            font-size: 8px;
            white-space: nowrap;
            padding: 0px 0px;
        }
 
        .new-price {
            font-weight: bold;
            color: #0a8a0a;
            font-size: 8px;
            white-space: nowrap;
            padding: 0px 0px;
        }
 
        .page-break {
            page-break-after: always;
            clear: both
        }
 
        .content-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
 th{font-size: 12px;}
        .col-product {
            width: 30%;
        }
 
        .col-image {
            width: 18%;
            text-align: center;
        }
 
        .col-code {
            width: 20%;
        }
 
        .col-price {
            width: 30%;
        }
 
        .price-cell {
            padding: 0px 5px;
        }
 
        .item-code {
            max-height: 40px;
            overflow: hidden;
            line-height: 12px;
        }
 
        .product-img {
            max-width: 70px;
            max-height: 30px;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: block;
            margin: 0 auto;
            width: 100%;
            height: 100%;
        }
        .price-cell br {
            display: none;
        }
    </style>
</head>
 
<body>
    {{-- HEADER --}}
    <table width="100%" class="header-table">
        <tr>
            <td width="70%" valign="middle">
                <h2>Today's Price List</h2>
                <div class="date-time">
                    <span>
                        <img src="{{ public_path('assets/images/calendar.png') }}" width="14">
                        {{ $generatedAt->timezone('Asia/Kolkata')->format('d/m/Y') }}
                    </span>
                    <span>
                        <img src="{{ public_path('assets/images/clock.png') }}" width="14">
                        {{ $generatedAt->timezone('Asia/Kolkata')->format('H:i') }}
                    </span>
                </div>
            </td>
            <td width="30%" align="right" valign="middle">
                <img src="{{ public_path('assets/images/logo-xion.png') }}" height="50">
            </td>
        </tr>
    </table>
  
    @php
    $firstPageCount = 56;
    $otherPageCount = 60;
    $pages = collect();
    $pages->push($products->take($firstPageCount));
    $remaining = $products->skip($firstPageCount);
    foreach ($remaining->chunk($otherPageCount) as $chunk) {
    $pages->push($chunk);
    }
    @endphp
 
    @foreach($pages as $page)
    @php
    $items = $page->values();
    @endphp
    <table width="100%" class="content-wrapper">
        <tr>
            {{-- LEFT --}}
            <td width="49%" valign="top">
                <table class="content-table">
                    <thead>
                        <tr>
                            <th class="col-product">Product</th>
                            <th class="col-image">Image</th>
                            <th class="col-code">Code</th>
                            <th class="col-price">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for($i = 0; $i < $items->count(); $i += 2)
                            @php
                                $item = $items[$i];
                                $img = $item->image ?? '';
                                if ($img !== '' && str_starts_with($img, 'http')) {
                                    $imagePath = $img;
                                } elseif ($img !== '' && Storage::disk('media')->exists($img)) {
                                    $imagePath = Storage::disk('media')->path($img);
                                } elseif ($img !== '') {
                                    $imagePath = public_path('media/' . $img);
                                } else {
                                    $imagePath = '';
                                }
                            @endphp
                            <tr>
                                <td class="item-name">{{ $item->name }}</td>
                                <td>
                                    <div class="product-img" style="background-image:url('{{ $imagePath }}')">
                                    </div>
                                </td>
                                <td class="item-code"> @foreach(str_split($item->item_code, 14) as $chunk){{ $chunk }}<br>@endforeach</td>
                                <td class="price-cell">
                                    @if($item->regular_price > $item->selling_price)
                                    <span class="old-price"><img src="{{ public_path('assets/images/Dirham-Symbol-grey.png') }}" width="8"> {{ number_format($item->regular_price,2) }}</span><br>
                                    @endif
                                    <span class="new-price"><img src="{{ public_path('assets/images/Dirham-Symbol.png') }}" width="8"> {{ number_format($item->selling_price,2) }}</span>
                                </td>
                            </tr>
                            @endfor
                    </tbody>
                </table>
            </td>
            <td width="2%"></td>
            {{-- RIGHT --}}
            <td width="49%" valign="top">
                <table class="content-table">
                    <thead>
                        <tr>
                            <th class="col-product">Product</th>
                            <th class="col-image">Image</th>
                            <th class="col-code">Code</th>
                            <th class="col-price">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for($i = 1; $i < $items->count(); $i += 2)
                            @php
                                $item = $items[$i];
                                $img = $item->image ?? '';
                                if ($img !== '' && str_starts_with($img, 'http')) {
                                    $imagePath = $img;
                                } elseif ($img !== '' && Storage::disk('media')->exists($img)) {
                                    $imagePath = Storage::disk('media')->path($img);
                                } elseif ($img !== '') {
                                    $imagePath = public_path('media/' . $img);
                                } else {
                                    $imagePath = '';
                                }
                            @endphp
                            <tr>
                                
                                <td class="item-name">{{ $item->name }}</td>
                                <td>
                                    <div class="product-img"
                                        style="background-image:url('{{ $imagePath }}')">
                                    </div>
                                </td>
                                <td class="item-code">@foreach(str_split($item->item_code, 14) as $chunk){{ $chunk }}<br>@endforeach</td>
                                <td class="price-cell">
                                    @if($item->regular_price > $item->selling_price)
                                    <span class="old-price"><img src="{{ public_path('assets/images/Dirham-Symbol-grey.png') }}" width="8"> {{ number_format($item->regular_price,2) }}</span><br>
                                    @endif
                                    <span class="new-price"><img src="{{ public_path('assets/images/Dirham-Symbol.png') }}" width="8"> {{ number_format($item->selling_price,2) }}</span>
                                </td>
                            </tr>
                            @endfor
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
    @if(!$loop->last)
    <div class="page-break"></div>
    @endif
    @endforeach
</body>
 
</html>
 