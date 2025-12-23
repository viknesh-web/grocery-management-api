<?php

namespace App\Http\Middleware;

use App\Models\Product;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckProduct
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get ID from route parameter or request payload
        $productId = $request->route('product') ?? $request->input('id');
        
        if ($productId) {
            $product = Product::find($productId);
            
            if (!$product) {
                return response()->json([
                    'message' => 'Product not found',
                ], 404);
            }
            
            $request->product = $product;
        }
        
        return $next($request);
    }
}

