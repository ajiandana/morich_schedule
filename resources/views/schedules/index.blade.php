@extends('layouts.app')

@section('content')
<div class="bg-white rounded shadow p-4 mb-6">
    <h2 class="font-semibold mb-3">Create Schedule</h2>

    <form id="createScheduleForm" class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div>
            <label class="text-sm">Order</label>
            <select name="order_id" class="w-full border rounded p-2">
                @foreach ($orders as $o)
                    <option value="{{ $o->id }}">{{ $o->order_no }} ({{ $o->qty_order }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-sm">Line</label>
            <select name="line_id" class="w-full border rounded p-2">
                @foreach ($lines as $l)
                    <option value="{{ $l->id }}">{{ $l->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-sm">Start</label>
            <input type="date" name="start_date" class="w-full border rounded p-2" required>
        </div>

        <div>
            <label class="text-sm">Finish</label>
            <input type="date" name="finish_date" class="w-full border rounded p-2" required>
        </div>

        <div>
            <label class="text-sm">Qty Total Target</label>
            <input type="number" name="qty_total_target" class="w-full border rounded p-2" min="1" required>
        </div>

        <div class="md:col-span-5 flex gap-3 items-center">
            <button id="btnCreate" type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">
                Create
            </button>
            <div id="createStatus" class="text-sm text-gray-600"></div>
        </div>
    </form>
</div>

<div class="bg-white rounded shadow p-4">
    <h2 class="font-semibold mb-3">Schedules</h2>

    <table class="w-full text-sm">
        <thead>
            <tr class="text-left border-b">
                <th class="py-2">Order</th>
                <th>Line</th>
                <th>Start</th>
                <th>Finish</th>
                <th>Qty Target</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($schedules as $s)
                <tr class="border-b">
                    <td class="py-2">{{ $s->order->order_no }}</td>
                    <td>{{ $s->line->name }}</td>
                    <td>{{ $s->start_date->toDateString() }}</td>
                    <td>{{ $s->finish_date->toDateString() }}</td>
                    <td>{{ $s->qty_total_target }}</td>
                    <td class="text-right">
                        <a class="text-blue-600 hover:underline" href="{{ route('schedules.show', $s) }}">Detail</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">
        {{ $schedules->links() }}
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('createScheduleForm');
    const btn = document.getElementById('btnCreate');
    const statusEl = document.getElementById('createStatus');

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        statusEl.textContent = 'Processing...';
        btn.disabled = true;
        btn.classList.add('opacity-60');

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            const res = await fetch('{{ route('schedules.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload)
            });

            const json = await res.json();

            if (!res.ok) {
                statusEl.textContent = json.message ?? 'Failed.';
                btn.disabled = false;
                btn.classList.remove('opacity-60');
                return;
            }

            statusEl.textContent = json.message ?? 'OK';
            //show new schedule in list
            window.location.reload();

        } catch (err) {
            console.error(err);
            statusEl.textContent = 'Network error';
        } finally {
            btn.disabled = false;
            btn.classList.remove('opacity-60');
        }
    });
})();
</script>
@endsection