<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');

            $table->unsignedInteger('target_qty');
            $table->unsignedInteger('actual_qty')->default(0);

            $table->timestamps();

            $table->unique(['schedule_id', 'work_date']);
            $table->index(['work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_days');
    }
};