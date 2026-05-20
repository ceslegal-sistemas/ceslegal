<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias_rit', function (Blueprint $table) {
            // Estado del proceso de mejora automática post-auditoría
            $table->string('estado_mejora', 20)->default('no_aplica')->after('fuente');
            // FK al RIT mejorado generado a partir de esta auditoría
            $table->foreignId('reglamento_mejorado_id')
                ->nullable()
                ->after('estado_mejora')
                ->constrained('reglamentos_internos')
                ->nullOnDelete();
            // Texto del RIT auditado — persiste el RIT externo en BD
            // (antes se usaba cache que podía expirar antes de que el job de mejora corriera)
            $table->longText('texto_auditado')->nullable()->after('reglamento_mejorado_id');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias_rit', function (Blueprint $table) {
            $table->dropForeign(['reglamento_mejorado_id']);
            $table->dropColumn(['estado_mejora', 'reglamento_mejorado_id', 'texto_auditado']);
        });
    }
};
