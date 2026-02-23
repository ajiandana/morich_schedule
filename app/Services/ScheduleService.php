<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function __construct(
        private WorkdayCalendar $calendar
    ) {}

    //Buat jadwal + generate jadwal hari kerja (schedule_days)
    public function createSchedule(array $data): Schedule
    {
        return DB::transaction(function () use ($data) {
            $start = CarbonImmutable::parse($data['start_date'])->toDateString();
            $finish = CarbonImmutable::parse($data['finish_date'])->toDateString();

            if ($finish < $start) {
                throw ValidationException::withMessages([
                    'finish_date' => 'Finish date must be >= start date.',
                ]);
            }

            //Check validasi overlap jadwal di line yg sama
            $overlap = Schedule::query()
                ->where('line_id', $data['line_id'])
                ->where(function ($q) use ($start, $finish) {
                    $q->whereBetween('start_date', [$start, $finish])
                        ->orWhereBetween('finish_date', [$start, $finish])
                        ->orWhere(function ($q2) use ($start, $finish) {
                            $q2->where('start_date', '<=', $start)
                               ->where('finish_date', '>=', $finish);
                        });
                })
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'line_id' => 'Schedule overlaps with existing schedule on this line.',
                ]);
            }

            $workdays = $this->calendar->weekdaysInRange($start, $finish);

            // after $workdays computed:
            $dates = array_map(fn($d) => $d->toDateString(), $workdays);

            $overlap = Schedule::query()
                ->where('line_id', $data['line_id'])
                ->whereHas('days', function ($q) use ($dates) {
                    $q->whereIn('work_date', $dates);
                })
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'line_id' => 'Schedule overlaps (workdays) with existing schedule on this line.',
                ]);
            }

            $schedule = Schedule::create([
                'order_id' => $data['order_id'],
                'line_id' => $data['line_id'],
                'start_date' => $start,
                'finish_date' => $finish,
                'qty_total_target' => $data['qty_total_target'],
                'status' => 'planned',
            ]);

            $qty = (int) $data['qty_total_target'];
            $n = count($workdays);
            $base = intdiv($qty, $n);
            $rem = $qty % $n;

            foreach ($workdays as $i => $d) {
                $target = $base;
                if ($i === $n - 1) {
                    $target += $rem; // remainder on last day
                }

                ScheduleDay::create([
                    'schedule_id' => $schedule->id,
                    'work_date' => $d->toDateString(),
                    'target_qty' => $target,
                    'actual_qty' => 0,
                ]);
            }

            return $schedule->load(['order', 'line', 'days' => fn($q) => $q->orderBy('work_date')]);
        });
    }

    public function updateActual(int $scheduleId, string $workDate, int $actualQty): array
    {
        return DB::transaction(function () use ($scheduleId, $workDate, $actualQty) {
            /** @var Schedule $schedule */
            $schedule = Schedule::query()
                ->lockForUpdate()
                ->with(['days' => fn($q) => $q->orderBy('work_date')])
                ->findOrFail($scheduleId);

            $workDate = CarbonImmutable::parse($workDate)->toDateString();

            /** @var ScheduleDay|null $day */
            $day = ScheduleDay::query()
                ->where('schedule_id', $schedule->id)
                ->whereDate('work_date', $workDate)
                ->lockForUpdate()
                ->first();

            if (!$day) {
                throw ValidationException::withMessages([
                    'work_date' => 'Work date not found in this schedule.',
                ]);
            }

            if ($actualQty < 0) {
                throw ValidationException::withMessages([
                    'actual_qty' => 'Actual must be >= 0.',
                ]);
            }

            if ($actualQty > $day->target_qty) {
                throw ValidationException::withMessages([
                    'actual_qty' => 'Actual cannot exceed target for that day.',
                ]);
            }

            $day->actual_qty = $actualQty;
            $day->save();

            $shortage = $day->target_qty - $day->actual_qty;

            $updatedDays = [];
            $createdDay = null;
            $shiftedSchedules = [];

            $oldFinish = $schedule->finish_date->toDateString();

            if ($shortage > 0) {
                $updatedDays = $this->distributeShortageToEligibleFutureDays($schedule->id, $workDate, $shortage);

                if (count($updatedDays) === 0) {
                    $createdDay = $this->extendByOneWeekdayWithTarget($schedule->id, $shortage);
                }
            }

            // reload schedule karena finish_date mungkin berubah
            $schedule->refresh();

            $newFinish = CarbonImmutable::parse($schedule->finish_date)->toDateString();
            if ($newFinish !== $oldFinish) {
                $deltaDays = CarbonImmutable::parse($newFinish)->diffInDays(CarbonImmutable::parse($oldFinish));
                // cascade shifts next schedules (and their days)
                $shiftedSchedules = $this->cascadeShiftNextSchedules(
                    lineId: (int) $schedule->line_id,
                    afterDate: $oldFinish,
                    deltaDays: $deltaDays
                );
            }

            return [
                'schedule' => $schedule->load(['order', 'line']),
                'updated_days' => $updatedDays,
                'created_day' => $createdDay,
                'shifted_schedules' => $shiftedSchedules,
            ];
        });
    }

    private function distributeShortageToEligibleFutureDays(int $scheduleId, string $workDate, int $shortage): array
    {
        $eligible = ScheduleDay::query()
            ->where('schedule_id', $scheduleId)
            ->where('work_date', '>', $workDate)
            ->where('actual_qty', '=', 0)
            ->orderBy('work_date')
            ->lockForUpdate()
            ->get();

        $r = $eligible->count();
        if ($r === 0) {
            return [];
        }

        $addBase = intdiv($shortage, $r);
        $addRem = $shortage % $r;

        $updated = [];
        foreach ($eligible as $idx => $d) {
            $add = $addBase;
            if ($idx === $r - 1) {
                $add += $addRem; // reminder on last eligible day
            }
            if ($add > 0) {
                $d->target_qty += $add;
                $d->save();
                $updated[] = $d->only(['work_date', 'target_qty', 'actual_qty']);
            }
        }

        return $updated;
    }

    private function extendByOneWeekdayWithTarget(int $scheduleId, int $targetQty): array
    {
        /** @var Schedule $schedule */
        $schedule = Schedule::query()->lockForUpdate()->findOrFail($scheduleId);

        $newDate = $this->calendar->nextWeekday($schedule->finish_date->toDateString())->toDateString();

        $day = ScheduleDay::create([
            'schedule_id' => $schedule->id,
            'work_date' => $newDate,
            'target_qty' => $targetQty,
            'actual_qty' => 0,
        ]);

        $schedule->finish_date = $newDate;
        $schedule->save();

        return $day->only(['work_date', 'target_qty', 'actual_qty']);
    }

    private function cascadeShiftNextSchedules(int $lineId, string $afterDate, int $deltaDays): array
    {
        $nextSchedules = Schedule::query()
            ->where('line_id', $lineId)
            ->where('start_date', '>', $afterDate)
            ->orderBy('start_date')
            ->lockForUpdate()
            ->get();

        if ($nextSchedules->isEmpty()) {
            return [];
        }

        $shifted = [];
        foreach ($nextSchedules as $s) {
            $oldStart = CarbonImmutable::parse($s->start_date)->toDateString();
            $oldFinish = CarbonImmutable::parse($s->finish_date)->toDateString();

            $newStart = $this->shiftDateByNWeekdays($oldStart, $deltaDays)->toDateString();
            $newFinish = $this->shiftDateByNWeekdays($oldFinish, $deltaDays)->toDateString();

            $s->start_date = $newStart;
            $s->finish_date = $newFinish;
            $s->save();

            $days = ScheduleDay::query()
                ->where('schedule_id', $s->id)
                ->orderBy('work_date')
                ->lockForUpdate()
                ->get();

            foreach ($days as $d) {
                $d->work_date = $this->shiftDateByNWeekdays($d->work_date->toDateString(), $deltaDays)->toDateString();
                $d->save();
            }

            $shifted[] = [
                'schedule_id' => $s->id,
                'delta_days' => $deltaDays,
                'new_start_date' => $newStart,
                'new_finish_date' => $newFinish,
            ];
        }

        return $shifted;
    }

    private function shiftDateByNWeekdays(string $date, int $n): CarbonImmutable
    {
        $d = CarbonImmutable::parse($date)->startOfDay();
        for ($i = 0; $i < $n; $i++) {
            $d = $this->calendar->nextWeekday($d->toDateString());
        }
        return $d;
    }
}