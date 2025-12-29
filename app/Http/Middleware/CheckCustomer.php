<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;

class CheckCustomer
{
    public function handle($request, Closure $next)
    {
        $customerId = $request->route('customer') ?? $request->input('id');
        
        if ($customerId && intval($customerId)) {
            $id = intval($customerId);
            $item = Customer::find($id);
            
            if (!$item) {
                return response()->json(['message' => 'Customer not found'], 404);
            }
            
            $request->attributes->set('customer', $item);
        }
        
        return $next($request);
    }
}

