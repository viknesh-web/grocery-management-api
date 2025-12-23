<?php

namespace App\Http\Middleware;

use App\Models\Category;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCategory
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get ID from route parameter or request payload
        $categoryId = $request->route('category') ?? $request->input('id');
        
        if ($categoryId) {
            $category = Category::find($categoryId);
            
            if (!$category) {
                return response()->json([
                    'message' => 'Category not found',
                ], 404);
            }
            
            $request->category = $category;
        }
        
        return $next($request);
    }
}

