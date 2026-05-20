<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            // Versión del RIT: 1 = original, 2+ = mejorado por auditoría
            $table->unsignedSmallInteger('version')->default(1)->after('fuente');
            // FK a la auditoría que originó esta versión mejorada (null en v1)
            $table->foreignId('auditoria_origen_id')
                ->nullable()
                ->after('version')
                ->constrained('auditorias_rit')
                ->nullOnDelete();
            // FK al RIT previo del que se deriva esta versión (auto-referencial)
            $table->foreignId('reglamento_origen_id')
                ->nullable()
                ->after('auditoria_origen_id')
                ->constrained('reglamentos_internos')
                ->nullOnDelete();
            // Ruta permanente del PDF en storage/app/private/
            $table->string('ruta_pdf')->nullable()->after('ruta_docx');
        });
    }

    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropForeign(['auditoria_origen_id']);
            $table->dropForeign(['reglamento_origen_id']);
            $table->dropColumn(['version', 'auditoria_origen_id', 'reglamento_origen_id', 'ruta_pdf']);
        });
    }
};
