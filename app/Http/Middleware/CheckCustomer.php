<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCustomer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get ID from route parameter or request payload
        $customerId = $request->route('customer') ?? $request->input('id');
        
        if ($customerId) {
            $customer = Customer::find($customerId);
            
            if (!$customer) {
                return response()->json([
                    'message' => 'Customer not found',
                ], 404);
            }
            
            $request->customer = $customer;
        }
        
        return $next($request);
    }
}

