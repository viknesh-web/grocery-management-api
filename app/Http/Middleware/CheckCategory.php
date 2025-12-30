<?php

namespace App\Http\Middleware;

use App\Models\Category;
use Closure;
use Illuminate\Http\Request;

class CheckCategory
{
    public function handle($request, Closure $next)
    {
        $categoryId = $request->route('category') ?? (isset($request->id) ? $request->id : null);
        
        if ($categoryId && (is_numeric($categoryId) || is_int($categoryId))) {
            $id = is_int($categoryId) ? $categoryId : intval($categoryId);
            $item = Category::find($id);
            
            if (!$item) {
                return response()->json(['message' => 'Category not found'], 404);
            }
            
            $request->attributes->set('category', $item);
        }
        
        return $next($request);
    }
}

