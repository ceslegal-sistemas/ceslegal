<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            // Embedding vectorial de $proceso->hechos (gemini-embedding-001, 3072 dims).
            // Se calcula una sola vez y se reutiliza en todas las búsquedas RAG del proceso.
            $table->longText('hechos_embedding')->nullable()->after('hechos');

            // Hash md5 del texto de hechos al momento de calcular el embedding.
            // Si hechos cambia, el hash no coincide y se recalcula automáticamente.
            $table->string('hechos_md5', 32)->nullable()->after('hechos_embedding');
        });
    }

    public function down(): void
    {
        Schema::table('procesos_disciplinarios', function (Blueprint $table) {
            $table->dropColumn(['hechos_embedding', 'hechos_md5']);
        });
    }
};
