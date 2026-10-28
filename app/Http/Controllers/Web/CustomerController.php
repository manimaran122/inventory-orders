<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $customers = Customer::query()
            ->search($validated['search'] ?? null)
            ->withCount('orders')
            // withSum lets the database total each customer spend, avoiding a
            // query per row.
            ->withSum('orders', 'grand_total')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'search' => $validated['search'] ?? '',
        ]);
    }

    public function show(Customer $customer): View
    {
        $orders = $customer->orders()
            ->with(['items.product'])
            ->withCount('items')
            ->latest('created_at')
            ->latest('id')
            ->paginate(10);

        return view('customers.show', [
            'customer' => $customer,
            'orders' => $orders,
        ]);
    }
}
