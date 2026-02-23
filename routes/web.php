<?php

use App\Http\Controllers\LineController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('schedules.index'));

Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
Route::get('/schedules/{schedule}', [ScheduleController::class, 'show'])->name('schedules.show');
Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');

Route::post(
    '/schedules/{schedule}/days/{work_date}/actual',
    [ScheduleController::class, 'updateActual']
)->name('schedules.days.actual');
Route::resource('lines', LineController::class)->except(['show']);
Route::resource('orders', OrderController::class)->except(['show']);