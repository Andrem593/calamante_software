<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $clientId = $request->query('client_id');

        $products = Product::where('is_visible', true)
            ->where('status', 'A')
            ->where(function ($query) {
                $query->where('stock', '>', 0)
                    ->orWhere('price', '<=', 0.0001);
            })
            ->get(['id', 'name', 'price', 'description', 'stock', 'tax_percentage', 'category_id']);

        if ($clientId) {
            $specialPrices = \Illuminate\Support\Facades\DB::table('special_prices')
                ->where('client_id', $clientId)
                ->get()
                ->keyBy('product_id');

            foreach ($products as $product) {
                if (isset($specialPrices[$product->id])) {
                    $special = $specialPrices[$product->id];
                    if ($special->price !== null) {
                        $product->price = (float)$special->price;
                    }
                    $product->discount_percentage = (float)$special->discount_percentage;
                } else {
                    $product->discount_percentage = 0.00;
                }
            }
        } else {
            foreach ($products as $product) {
                $product->discount_percentage = 0.00;
            }
        }

        return $products;
    }

    public function categories()
    {
        return \App\Models\Category::whereHas('products', function ($query) {
            $query->where('is_visible', true)
                ->where('status', 'A')
                ->where(function ($q) {
                    $q->where('stock', '>', 0)
                        ->orWhere('price', '<=', 0.0001);
                });
        })->get(['id', 'name']);
    }

    public function show($id)
    {
        return Product::findOrFail($id);
    }
}
