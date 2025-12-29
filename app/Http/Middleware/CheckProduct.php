<?php

namespace App\Http\Middleware;

use App\Models\Product;
use Closure;
use Illuminate\Http\Request;

class CheckProduct
{
    public function handle($request, Closure $next)
    {
        $productId = $request->route('product') ?? $request->input('id');
        
        if ($productId && intval($productId)) {
            $id = intval($productId);
            $item = Product::find($id);
            
            if (!$item) {
                return response()->json(['message' => 'Product not found'], 404);
            }
            
            $request->attributes->set('product', $item);
        }
        
        return $next($request);
    }
}

