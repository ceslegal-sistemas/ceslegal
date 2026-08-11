<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sancion_process_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('procesos_disciplinarios')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type'); // risk_acknowledged | decision_selected | authorized | submitted
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sancion_process_events');
    }
};
