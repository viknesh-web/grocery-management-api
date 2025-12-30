<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;

class CheckCustomer
{
    public function handle($request, Closure $next)
    {
        // Get customer ID from route parameter (matches hifit-erp-server pattern)
        $customerId = $request->route('customer') ?? (isset($request->id) ? $request->id : null);
        
        if ($customerId && (is_numeric($customerId) || is_int($customerId))) {
            $id = is_int($customerId) ? $customerId : intval($customerId);
            $item = Customer::find($id);
            
            if (!$item) {
                return response()->json(['message' => 'Customer not found'], 404);
            }
            
            $request->attributes->set('customer', $item);
        }
        
        return $next($request);
    }
}

