<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function details(Product $product)
    {
        return view('front.details', compact('product'));
    }

    public function booking(Product $product)
    {
        $stores = Store::all();
        return view('front.booking', compact('product', 'stores'));
    }

    public function booking_save(StoreBookingRequest $request, Product $product)
    {
        session()->put('product_id', $product->id);

        $bookingData = $request->only(['duration', 'started_at', 'store_id', 'delivery_type', 'address']);

        session($bookingData);

        return redirect()->route('front.checkout', $product->slug);
    }

    public function checkout(Product $product)
    {
        $duration = session('duration');

        $insurance = 900000;
        $ppn = 0.11;
        $price = $product->price;

        $subTotal = $price * $duration;
        $totalPpn = $subTotal * $ppn;
        $grandTotal = $subTotal + $totalPpn + $insurance;

        return view('front.checkout', compact('product', 'subTotal', 'totalPpn', 'grandTotal', 'insurance'));
    }

    public function checkout_store(StorePaymentRequest $request)
    {
        $bookingData = session()->only(['duration', 'started_at', 'store_id', 'delivery_type', 'address', 'product_id']);

        $duration = (int) $bookingData['duration'];
        $startedDate = Carbon::parse($bookingData['started_at']);

        $productDetails = Product::find($bookingData['product_id']);
        if (!$productDetails) {
            return back()->withErrors(['product_id' => 'Product not found.']);
        }

        $insurance = 900000;
        $ppn = 0.11;
        $price = $productDetails->price;

        $subTotal = $price * $duration;
        $totalPpn = $subTotal * $ppn;
        $grandTotal = $subTotal + $totalPpn + $insurance;

        $bookingTransactionId = null;

        DB::transaction(function () use ($request, &$bookingTransactionId, $duration, $bookingData, $grandTotal, $productDetails, $startedDate) {
            $validated = $request->validated();

            if ($request->hasFile('proof')) {
                $proofPath = $request->file('proof')->store('proofs', 'public');
                $validated['proof'] = $proofPath;
            }

            $endedDate = $startedDate->copy()->addDays($duration); /* cara bikin ended_date */

            $validated['started_at'] = $startedDate;
            $validated['ended_at'] = $endedDate;
            $validated['duration'] = $duration;
            $validated['total_amount'] = $grandTotal;
            $validated['store_id'] = $bookingData['store_id'];
            $validated['product_id'] = $productDetails['id'];
            $validated['delivery_type'] = $bookingData['delivery_type'];
            $validated['address'] = $bookingData['address'];
            $validated['is_paid'] = false;
            $validated['trx_id'] = Transaction::generateUniqueTrxId();

            $newBooking = Transaction::create($validated);

            $bookingTransactionId = $newBooking['id'];
        });

        return redirect()->route('front.success.booking', $bookingTransactionId);
    }

    public function success_booking(Transaction $transaction)
    {
        return view('front.success_booking', compact('transaction'));
    }

    public function transactions()
    {
        return view('front.transactions');
    }

    public function transactions_details(Request $request)
    {
        $request->validate([
            'trx_id' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:255'],
        ]);

        $trx_id = $request->input('trx_id');
        $phone_number = $request->input('phone_number');

        $details = Transaction::with(['store', 'product'])
            ->where('trx_id', $trx_id)
            ->where('phone_number', $phone_number)
            ->first();

        if (!$details) {
            return back()->withErrors(['error' => 'Transactions not found.']);
        }

        $ppn = 0.11;
        $insurance = 900000;
        $totalPpn = $details->product->price * $ppn;
        $duration = $details->duration;
        $subTotal = $details->product->price * $duration;

        return view('front.transaction_details', compact('details', 'totalPpn', 'subTotal', 'insurance'));
    }
}
