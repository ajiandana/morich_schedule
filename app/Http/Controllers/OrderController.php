<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::query()->orderByDesc('id')->paginate(20);
        return view('orders.index', compact('orders'));
    }

    public function create()
    {
        return view('orders.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_no' => ['required', 'string', 'max:100', 'unique:orders,order_no'],
            'style' => ['nullable', 'string', 'max:255'],
            'buyer' => ['nullable', 'string', 'max:255'],
            'qty_order' => ['required', 'integer', 'min:1'],
            'due_date' => ['nullable', 'date'],
        ]);

        Order::create($data);
        return redirect()->route('orders.index')->with('status', 'Order created.');
    }

    public function edit(Order $order)
    {
        return view('orders.edit', compact('order'));
    }

    public function update(Request $request, Order $order)
    {
        $data = $request->validate([
            'order_no' => ['required', 'string', 'max:100', 'unique:orders,order_no,' . $order->id],
            'style' => ['nullable', 'string', 'max:255'],
            'buyer' => ['nullable', 'string', 'max:255'],
            'qty_order' => ['required', 'integer', 'min:1'],
            'due_date' => ['nullable', 'date'],
        ]);

        $order->update($data);
        return redirect()->route('orders.index')->with('status', 'Order updated.');
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return redirect()->route('orders.index')->with('status', 'Order deleted.');
    }
}