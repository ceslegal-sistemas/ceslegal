<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumenes_ejecutivos_rit_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reglamento_interno_id')->constrained('reglamentos_internos')->cascadeOnDelete();
            $table->string('hash_comparacion', 64)->unique();
            $table->text('resumen');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumenes_ejecutivos_rit_cambios');
    }
};
