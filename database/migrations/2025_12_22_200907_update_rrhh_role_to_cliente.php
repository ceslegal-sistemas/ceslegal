<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cambiar todos los usuarios con rol 'rrhh' a 'cliente'
        DB::table('users')
            ->where('role', 'rrhh')
            ->update(['role' => 'cliente']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir: cambiar todos los 'cliente' que antes eran 'rrhh' de vuelta a 'rrhh'
        // Nota: Esto no es perfecto porque no podemos distinguir entre clientes
        // originales y los que antes eran rrhh
        // Por simplicidad, no hacemos nada en el down
    }
};
