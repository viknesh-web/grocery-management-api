<?php

namespace App\Http\Middleware;

use App\Models\Product;
use Closure;
use Illuminate\Http\Request;

class CheckProduct
{
    public function handle($request, Closure $next)
    {
        // Get product ID from route parameter (matches hifit-erp-server pattern)
        $productId = $request->route('product') ?? (isset($request->id) ? $request->id : null);
        
        if ($productId && (is_numeric($productId) || is_int($productId))) {
            $id = is_int($productId) ? $productId : intval($productId);
            $item = Product::find($id);
            
            if (!$item) {
                return response()->json(['message' => 'Product not found'], 404);
            }
            
            $request->attributes->set('product', $item);
        }
        
        return $next($request);
    }
}

