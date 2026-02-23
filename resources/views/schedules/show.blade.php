@extends('layouts.app')

@section('content')
<div class="bg-white rounded shadow p-4 mb-6">
    <div class="flex items-start justify-between">
        <div>
            <h2 class="text-lg font-semibold">Schedule Detail</h2>
            <div class="text-sm text-gray-700 mt-1">
                <div><b>Order:</b> {{ $schedule->order->order_no }}</div>
                <div><b>Line:</b> {{ $schedule->line->name }}</div>
                <div><b>Start:</b> <span id="startDate">{{ $schedule->start_date->toDateString() }}</span></div>
                <div><b>Finish:</b> <span id="finishDate">{{ $schedule->finish_date->toDateString() }}</span></div>
                <div><b>Qty total target:</b> {{ $schedule->qty_total_target }}</div>
            </div>
        </div>
        <a class="text-blue-600 hover:underline" href="{{ route('schedules.index') }}">Back</a>
    </div>
</div>

<div class="bg-white rounded shadow p-4">
    <h3 class="font-semibold mb-3">Daily Plan</h3>
    <div id="saveStatus" class="text-sm text-gray-600 mb-3"></div>

    <table class="w-full text-sm" id="daysTable">
        <thead>
            <tr class="text-left border-b">
                <th class="py-2">Date</th>
                <th>Target</th>
                <th>Realisasi</th>
                <th>Selisih Kurang</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($schedule->days as $d)
                <tr class="border-b" data-work-date="{{ $d->work_date->toDateString() }}">
                    <td class="py-2">{{ $d->work_date->toDateString() }}</td>
                    <td class="target">{{ $d->target_qty }}</td>
                    <td>
                        <input class="actual border rounded p-1 w-24"
                               type="number"
                               min="0"
                               value="{{ $d->actual_qty }}"
                               data-max="{{ $d->target_qty }}">
                    </td>
                    <td class="shortage">{{ $d->target_qty - $d->actual_qty }}</td>
                    <td class="text-right">
                        <button class="btnSave bg-blue-600 text-white px-3 py-1 rounded">Save</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<script>
(function() {
    const table = document.getElementById('daysTable');
    const statusEl = document.getElementById('saveStatus');
    const finishEl = document.getElementById('finishDate');

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    }

    function setStatus(msg) {
        statusEl.textContent = msg;
    }

    function updateRow(workDate, newTarget, newActual) {
        const row = table.querySelector(`tr[data-work-date="${workDate}"]`);
        if (!row) return;

        row.querySelector('.target').textContent = newTarget;
        const actualInput = row.querySelector('.actual');
        actualInput.value = newActual;
        actualInput.dataset.max = newTarget;
        row.querySelector('.shortage').textContent = (newTarget - newActual);
    }

    function appendRow(day) {
        const tr = document.createElement('tr');
        tr.className = 'border-b';
        tr.dataset.workDate = day.work_date;
        tr.setAttribute('data-work-date', day.work_date);

        tr.innerHTML = `
            <td class="py-2">${day.work_date}</td>
            <td class="target">${day.target_qty}</td>
            <td>
                <input class="actual border rounded p-1 w-24"
                       type="number" min="0"
                       value="${day.actual_qty}"
                       data-max="${day.target_qty}">
            </td>
            <td class="shortage">${day.target_qty - day.actual_qty}</td>
            <td class="text-right">
                <button class="btnSave bg-blue-600 text-white px-3 py-1 rounded">Save</button>
            </td>
        `;
        table.querySelector('tbody').appendChild(tr);
    }

    table.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btnSave');
        if (!btn) return;

        const row = e.target.closest('tr[data-work-date]');
        const workDate = row.getAttribute('data-work-date');
        const target = parseInt(row.querySelector('.target').textContent, 10);
        const actualInput = row.querySelector('.actual');
        const actual = parseInt(actualInput.value || '0', 10);

        if (actual > target) {
            setStatus(`Actual tidak boleh melebihi target (${target}).`);
            return;
        }

        btn.disabled = true;
        btn.classList.add('opacity-60');
        setStatus('Saving...');

        try {
            const url = `{{ url('/schedules/' . $schedule->id) }}/days/${workDate}/actual`;
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ actual_qty: actual })
            });

            const json = await res.json();
            if (!res.ok) {
                setStatus(json.message ?? 'Failed.');
                return;
            }

            setStatus(json.message ?? 'OK');

            const data = json.data;

            // Update finish date jika ada perubahan
            if (data.schedule?.finish_date) {
                finishEl.textContent = data.schedule.finish_date;
            }

            // Apply updated days (target berubah)
            if (Array.isArray(data.updated_days)) {
                data.updated_days.forEach(d => updateRow(d.work_date, d.target_qty, d.actual_qty));
            }

            // created new day (tambahan hari baru jika finish date maju)
            if (data.created_day) {
                appendRow(data.created_day);
            }

            // refresh
            updateRow(workDate, target, actual);

            if (Array.isArray(data.shifted_schedules) && data.shifted_schedules.length > 0) {
                setStatus((json.message ?? 'OK') + ` | Next schedules shifted: ${data.shifted_schedules.length}`);
            }
        } catch (err) {
            console.error(err);
            setStatus('Network error');
        } finally {
            btn.disabled = false;
            btn.classList.remove('opacity-60');
        }
    });
})();
</script>
@endsection