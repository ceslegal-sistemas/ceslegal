<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE analisis_juridicos MODIFY COLUMN tipo_sancion_recomendada ENUM('llamado_atencion','suspension','terminacion','no_sancion') NULL");
        DB::statement("ALTER TABLE impugnaciones MODIFY COLUMN nueva_sancion_tipo ENUM('llamado_atencion','suspension','terminacion','no_sancion') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE analisis_juridicos MODIFY COLUMN tipo_sancion_recomendada ENUM('llamado_atencion','suspension','terminacion') NULL");
        DB::statement("ALTER TABLE impugnaciones MODIFY COLUMN nueva_sancion_tipo ENUM('llamado_atencion','suspension','terminacion') NULL");
    }
};
