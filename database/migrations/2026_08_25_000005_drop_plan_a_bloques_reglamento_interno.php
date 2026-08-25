<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bloques_reglamento_interno');

        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn(['bloques_texto_hash', 'bloques_generados_en']);
        });
    }

    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->string('bloques_texto_hash', 64)->nullable()->after('texto_completo');
            $table->timestamp('bloques_generados_en')->nullable()->after('bloques_texto_hash');
        });

        Schema::create('bloques_reglamento_interno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reglamento_interno_id')->constrained('reglamentos_internos')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->text('contenido');
            $table->json('embedding')->nullable();
            $table->timestamps();

            $table->index(['reglamento_interno_id', 'orden']);
        });
    }
};
