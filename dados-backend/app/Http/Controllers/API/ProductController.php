<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return Product::where('is_visible', true)
            ->where('status', 'A')
            ->where(function ($query) {
                $query->where('stock', '>', 0)
                    ->orWhere('price', 0);
            })
            ->get(['id', 'name', 'price', 'description', 'stock', 'tax_percentage', 'category_id']);
    }

    public function categories()
    {
        return \App\Models\Category::whereHas('products', function ($query) {
            $query->where('is_visible', true)
                ->where('status', 'A')
                ->where(function ($q) {
                    $q->where('stock', '>', 0)
                        ->orWhere('price', 0);
                });
        })->get(['id', 'name']);
    }

    public function show($id)
    {
        return Product::findOrFail($id);
    }
}
