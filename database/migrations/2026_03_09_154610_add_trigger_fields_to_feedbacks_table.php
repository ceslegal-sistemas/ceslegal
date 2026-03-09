<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->string('trigger', 30)->nullable()->after('tipo');
            $table->unsignedTinyInteger('nps_score')->nullable()->after('calificacion');
            $table->json('respuestas_adicionales')->nullable()->after('sugerencia');
            $table->unsignedTinyInteger('calificacion')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->dropColumn(['trigger', 'nps_score', 'respuestas_adicionales']);
            $table->unsignedTinyInteger('calificacion')->nullable(false)->change();
        });
    }
};
