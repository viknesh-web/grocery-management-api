<?php

namespace App\Http\Middleware;

use App\Models\Order;
use Closure;
use Illuminate\Http\Request;

class CheckOrder
{
    public function handle($request, Closure $next)
    {
        $orderId = $request->route('order') ?? (isset($request->id) ? $request->id : null);
        
        if ($orderId && (is_numeric($orderId) || is_int($orderId))) {
            $id = is_int($orderId) ? $orderId : intval($orderId);
            $item = Order::find($id);
            
            if (!$item) {
                return response()->json(['message' => 'Order not found'], 404);
            }
            
            $request->attributes->set('order', $item);
        }
        
        return $next($request);
    }
}