<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            // Token único público para verificar el documento (URL-safe UUID)
            $table->string('verificacion_token', 64)->nullable()->unique()->after('evidencia_metadata');
            // Hash SHA-256 de los metadatos clave del documento en el momento de generación
            $table->string('verificacion_hash', 64)->nullable()->after('verificacion_token');
            // Cuándo se generó el documento con QR
            $table->timestamp('verificacion_generada_en')->nullable()->after('verificacion_hash');
        });
    }

    public function down(): void
    {
        Schema::table('diligencias_descargos', function (Blueprint $table) {
            $table->dropColumn(['verificacion_token', 'verificacion_hash', 'verificacion_generada_en']);
        });
    }
};
