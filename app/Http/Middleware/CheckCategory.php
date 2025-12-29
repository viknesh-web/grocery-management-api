<?php

namespace App\Http\Middleware;

use App\Models\Category;
use Closure;
use Illuminate\Http\Request;

class CheckCategory
{
    public function handle($request, Closure $next)
    {
        $categoryId = $request->route('category') ?? $request->input('id');
        
        if ($categoryId && intval($categoryId)) {
            $id = intval($categoryId);
            $item = Category::find($id);
            
            if (!$item) {
                return response()->json(['message' => 'Category not found'], 404);
            }
            
            $request->attributes->set('category', $item);
        }
        
        return $next($request);
    }
}

