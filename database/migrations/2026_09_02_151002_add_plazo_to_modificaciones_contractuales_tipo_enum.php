<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega 'plazo' al ENUM de tipo_modificacion. En MySQL, vía SQL crudo (no
 * Schema::table()->enum()->change(), que via Doctrine puede reescribir la
 * columna de forma imprevisible en un ENUM de MySQL) - mismo cuidado que ya
 * costó 2 incidentes reales en este proyecto al tocar este mismo ENUM
 * (truncamiento silencioso del INSERT si se agrega un valor al modelo sin
 * ampliar también la columna real en la BD).
 *
 * SQLite (tests) SÍ aplica el enum() de Laravel como un CHECK constraint
 * real - confirmado empíricamente (un test insertando 'plazo' fallaba con
 * "CHECK constraint failed" hasta agregar esta rama). Ahí sí se usa
 * ->change() vía Doctrine: no aplica el riesgo de truncamiento silencioso
 * de MySQL (SQLite recrea la tabla completa, no trunca en silencio) y solo
 * corre contra la BD de pruebas en memoria, nunca contra datos reales.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('modificaciones_contractuales', function (Blueprint $table) {
                $table->enum('tipo_modificacion', ['salario', 'cargo', 'jornada', 'tipo_contrato', 'plazo'])->change();
            });
            return;
        }

        DB::statement("ALTER TABLE modificaciones_contractuales MODIFY tipo_modificacion ENUM('salario', 'cargo', 'jornada', 'tipo_contrato', 'plazo') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('modificaciones_contractuales', function (Blueprint $table) {
                $table->enum('tipo_modificacion', ['salario', 'cargo', 'jornada', 'tipo_contrato'])->change();
            });
            return;
        }

        DB::statement("ALTER TABLE modificaciones_contractuales MODIFY tipo_modificacion ENUM('salario', 'cargo', 'jornada', 'tipo_contrato') NOT NULL");
    }
};
