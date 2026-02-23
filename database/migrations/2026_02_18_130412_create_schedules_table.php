<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();

            $table->date('start_date');
            $table->date('finish_date');

            $table->unsignedInteger('qty_total_target');

            $table->string('status')->default('planned'); // planned|running|done
            $table->timestamps();

            $table->index(['line_id', 'start_date', 'finish_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};