<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prueba de intervención humana en la emisión de la sanción: la traza de
 * eventos ya registraba QUIÉN y CUÁNDO, pero no DESDE DÓNDE. Sin IP ni
 * user agent, un registro de auditoría no permite sostener ante un juez
 * que la decisión la tomó una persona real desde un dispositivo concreto
 * - que es exactamente el objetivo de esta traza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sancion_process_events', function (Blueprint $table) {
            // 45 caracteres: cabe una IPv6 completa (39) y una IPv4 mapeada
            // en IPv6 ("::ffff:192.168.1.1", 45 en el peor caso).
            $table->string('ip', 45)->nullable()->after('event_type');
            $table->text('user_agent')->nullable()->after('ip');
        });
    }

    public function down(): void
    {
        Schema::table('sancion_process_events', function (Blueprint $table) {
            $table->dropColumn(['ip', 'user_agent']);
        });
    }
};
