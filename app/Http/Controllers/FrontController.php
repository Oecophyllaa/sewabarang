<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class FrontController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        $latest_products = Product::latest()->take(4)->get();
        $random_products = Product::inRandomOrder()->take(6)->get();
        return view('front.index', compact('categories', 'latest_products', 'random_products'));
    }

    public function category(Category $category)
    {
        session()->put('category_id', $category->id);
        return view('front.brands', compact('category'));
    }

    public function brand(Brand $brand)
    {
        $category_id = session()->get('category_id');

        $products = Product::where('brand_id', $brand->id)
            ->where('category_id', $category_id)
            ->latest()
            ->get();

        return view('front.gadgets', compact('brand', 'products'));
    }
}
