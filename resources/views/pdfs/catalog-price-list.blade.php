<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
       
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins';
            background-image: url('{{ public_path("assets/images/pdf-background.png") }}');
            background-repeat: no-repeat;
            background-color: #F3EDEF;
            background-position: center;
            background-size: cover;
            padding: 20px;
        }
 
        .page-top{margin-top:120px;}
       
        .header {
        background: white;
        padding: 20px;
        border-radius: 8px;
        overflow: hidden;
    }
   
   
    .header-right {
        float: right;
        width: 30%;
        text-align: right;
    }
   
    .header h1 {
        font-size: 28px;
        color: #2C3E50;
        margin-bottom: 0px;
    }
   
    .header-subtitle {
        color: #718096;
        font-size: 11px;
        line-height: 1.6;
    }
   
    .logo {
        height: 100px;
        max-width: 180px;
    }
   
    .clearfix::after {
        content: "";
        display: table;
        clear: both;
    }
       
.product-table {
    width: 100%;
    min-height: 50px;
    border-spacing: 18px;
}
.product-cell {
    width: 100%;
    vertical-align: top;
    padding: 0px;
}
 
.product-card {
    width: 100%;
    height: 160px;
    background: #ffffff;
    border-radius: 20px;
    box-shadow: rgba(0, 0, 0, 0.15) 0px 5px 15px 0px;
    padding: 0px;
    margin-right: 0px;
    table-layout: fixed;
}
.product-image-cell {
    width: 90px;
    height: 100px;
    padding: 0px;
    margin: 0px;
}
 
.product-image {
    max-width: 120px;
    max-height: 150px;
    width: 110px;
    height: 150px;
    padding: 0px;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    border-radius: 10px 0px 0px 10px;
}
 
.product-details {
    position: relative;
    padding: 10px 0px 10px 10px;
}
 
.category-badge {
    display: inline-block;  
    color: #ffffff;
    font-size: 14px;
    background: url('{{ public_path("assets/images/code-bg.png") }}') no-repeat right center;
    background-size: 95px;
    padding: 10px 5px 10px 15px;
    margin: 5px -15px 5px 0px;
    object-fit: cover;
    text-align: center;
    width: 95px;
    min-width: 95px;
    max-width: 95px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    box-sizing: border-box;
    vertical-align: middle;
    line-height: 1.2;
    font-family: 'Poppins';
}

.badge-wrapper {
    text-align: right;
    width: 100%;
    margin: 5px 0px;
}

.calendar-icon{
    width: 20px;
    height: 20px;
    background-image: url('{{ public_path("assets/images/calender-icon.png") }}');
    background-size: contain;
    background-repeat: no-repeat;
    padding-left:20px;
    font-size:14px;
}
.clock-icon{
    width: 20px;
    height: 20px;
    background-image: url('{{ public_path("assets/images/clock-icon.png") }}');
    background-size: contain;
    background-repeat: no-repeat;
    padding-left:20px;
    margin-left:20px;
    font-size:14px;
}
.phone-icon{
    width: 30px;
    height: 30px;
    background-image: url('{{ public_path("assets/images/whatsapp-icon.png") }}');
    background-repeat: no-repeat;
    padding-left:35px;
    padding-top:5px;
    padding-bottom:5px;
    font-size:16px;
    margin-bottom:20px;
    background-size: 23px;
    line-height:26px;
    background-position: left center;
}
.mail-icon{
    width: 30px;
    height: 30px;
    background-image: url('{{ public_path("assets/images/mail-icon.png") }}');
    background-repeat: no-repeat;
    padding-left:35px;
    font-size:16px;
    background-size: 23px;
    padding-top:5px;
    padding-bottom:5px;
    line-height:26px;
    background-position: left center;
}
.product-name {
    font-size: 16px;
    font-weight: 600;
    color: #2C3E50;
    font-family: 'Poppins';
}
 
.original-price {
    font-size: 10px;
    color: #7F8C8D;
    text-decoration: line-through;
}
.selling-price .unit {
    font-size: 13px;
    font-weight: 500;
    color: #6c757d;
    margin-left: 2px;
}
.selling-price {
    font-size: 15px;
    font-weight: bold;
    color: #3DA222;
}
.page-break {
    page-break-before: always;
}
 
.page-top-space {
    height: 100px; /* REQUIRED TOP SPACE */
}
 
    </style>
</head>
<body>
    <!-- Header -->
<table class="page-top" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
    <tr width="100%">
 
        <!-- LEFT COLUMN -->
          <td width="33%" style="vertical-align: top;">
            <h2 style="margin:0; font-size:24px; font-weight: bold; white-space: nowrap;">Todays Price List</h2>
            <div style="font-size:12px; color:#555; margin-top:5px;">
                <span class="calendar-icon">{{ $generatedAt->timezone('Asia/Kolkata')->format('d/m/Y') }}</span>
                <span class="clock-icon">{{ $generatedAt->timezone('Asia/Kolkata')->format('H:i') }}</span>
            </div>
        </td>
 
        <!-- CENTER COLUMN (LOGO) -->
         <td width="34%" align="center" style="vertical-align: middle;">
            @php
                $logoPaths = [
                    public_path('storage/pdf-logo.png'),
                    storage_path('app/public/pdf-logo.png'),
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
        </td>
 
        <!-- RIGHT COLUMN -->
 
        <td width="33%" style="vertical-align: top; text-align: right;">
            <h2 style="margin:0; font-size:24px; font-weight: bold; white-space: nowrap;">For Order</h2>
            <div style="font-size:14px; color:#555; margin-top:5px;">
                <div style="margin-bottom: 5px;">
                    <span class="phone-icon">+971 56 505 1125</span>
                </div>
                <div>
                    <span class="mail-icon">order@xion.com</span>
                </div>
            </div>
        </td>
 
    </tr>
</table>
 
 
 
    <!-- Products Grid -->
    <div class="products-grid">
       @foreach($products->chunk(3) as $rowIndex => $productChunk)
 
    @if($rowIndex > 0 && $rowIndex % 4 == 0)
        </div>
 
        <div class="page-break"></div>
 
        <!-- TOP SPACE FOR NEW PAGE -->
        <div class="page-top-space"></div>
 
        <div class="products-grid">
    @endif
           
            <table class="product-table">
    <tr>
        @foreach($productChunk as $product)
            <td class="product-cell">

                <table class="product-card">
                    <tr>
                        <!-- IMAGE -->
                        <td class="product-image-cell">
                            @php
                                $imagePath = null;
                                if (!empty($product->image_url)) {
                                    $relativePath = str_replace(url('/media').'/', '', $product->image_url);
                                    $imagePath = Storage::disk('media')->path($relativePath);
                                }else {
                                    $imagePath = public_path('assets/images/no-image.png');
                                }
                            @endphp
 
                            <div class="product-image"
                                style="background-image:url('{{ $imagePath }}')">
                            </div>
                        </td>
 
 
 
                        <!-- DETAILS -->
                        <td class="product-details">
                            <div class="product-name">
                                {{ $product->name }}
                            </div>
                            
                            {{-- RIGHT-ALIGNED BADGE WRAPPER --}}
                            <div class="badge-wrapper">
                                <div class="category-badge">
                                    <span>
                                        @php
                                            $itemCode = strtoupper($product->item_code);
                                            $maxLength = 7;
                                            if (strlen($itemCode) > $maxLength) {
                                                echo substr($itemCode, 0, $maxLength) . '..';
                                            } else {
                                                echo $itemCode;
                                            }
                                        @endphp
                                    </span>
                                </div>
                            </div>
                            
                            <div>
                            @if($product->discount_price > 0)
                                <div class="original-price">
                                     <img src="{{ public_path('assets/images/symbol.png') }}" width="8"> {{ number_format($product->original_price, 2) }}
                                </div>
                            @endif
 
                            <div class="selling-price margin:20px 0px;">
                                 <img src="{{ public_path('assets/images/green-symbol.png') }}" width="12"> {{ number_format($product->selling_price, 2) }} / {{ $product->stock_unit }}
                            </div>
                            </div>
                        </td>
 
                    </tr>
                </table>
 
            </td>
        @endforeach
 
        @for($i = count($productChunk); $i < 3; $i++)
            <td class="product-cell"></td>
        @endfor
    </tr>
</table>
 
        @endforeach
    </div>
 
    <!-- Footer -->
    {{-- <div class="footer">
        <p>Thank you for your business! | Prices are subject to change without notice</p>
        <p style="margin-top: 5px;">Generated automatically by Zion Grocery Management System</p>
    </div> --}}
</body>
</html>