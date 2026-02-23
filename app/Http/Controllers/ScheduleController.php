<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\Order;
use App\Models\Schedule;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    public function __construct(
        private ScheduleService $service
    ) {}

    public function index(Request $request)
    {
        $lines = Line::query()->where('is_active', true)->orderBy('name')->get();
        $orders = Order::query()->orderBy('order_no')->get();

        $schedules = Schedule::query()
            ->with(['line', 'order'])
            ->withSum('days as total_target', 'target_qty')
            ->withSum('days as total_actual', 'actual_qty')
            ->orderByDesc('id')
            ->paginate(20);

        return view('schedules.index', compact('lines', 'orders', 'schedules'));
    }

    public function show(Schedule $schedule)
    {
        $schedule->load([
            'line',
            'order',
            'days' => fn($q) => $q->orderBy('work_date'),
        ]);

        return view('schedules.show', compact('schedule'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'line_id' => ['required', 'exists:lines,id'],
            'start_date' => ['required', 'date'],
            'finish_date' => ['required', 'date'],
            'qty_total_target' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $schedule = $this->service->createSchedule($data);

            return response()->json([
                'success' => true,
                'message' => 'Schedule created.',
                'data' => $schedule,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function updateActual(Request $request, Schedule $schedule, string $work_date)
    {
        $data = $request->validate([
            'actual_qty' => ['required', 'integer', 'min:0'],
        ]);

        $result = $this->service->updateActual(
            scheduleId: (int) $schedule->id,
            workDate: $work_date,
            actualQty: (int) $data['actual_qty']
        );

        return response()->json([
            'success' => true,
            'message' => 'Actual saved and schedule balanced.',
            'data' => $result,
        ]);
    }
}
