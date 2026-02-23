@extends('layouts.app')

@section('content')
<div class="mb-4 flex justify-between items-center">
    <h2 class="text-lg font-semibold">Orders</h2>
    <a class="bg-blue-600 text-white px-4 py-2 rounded" href="{{ route('orders.create') }}">New Order</a>
</div>

@if (session('status'))
    <div class="mb-4 p-3 bg-green-100 border border-green-200 rounded text-sm">{{ session('status') }}</div>
@endif

<div class="bg-white rounded shadow p-4">
    <table class="w-full text-sm">
        <thead>
        <tr class="text-left border-b">
            <th class="py-2">Order No</th>
            <th>Style</th>
            <th>Buyer</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @foreach ($orders as $o)
            <tr class="border-b">
                <td class="py-2">{{ $o->order_no }}</td>
                <td>{{ $o->style }}</td>
                <td>{{ $o->buyer }}</td>
                <td class="text-right flex gap-2 justify-end py-2">
                    <a class="text-blue-600 hover:underline" href="{{ route('orders.edit', $o) }}">Edit</a>
                    <form method="POST" action="{{ route('orders.destroy', $o) }}" onsubmit="return confirm('Delete order?')">
                        @csrf
                        @method('DELETE')
                        <button class="text-red-600 hover:underline" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="mt-4">{{ $orders->links() }}</div>
</div>
@endsection