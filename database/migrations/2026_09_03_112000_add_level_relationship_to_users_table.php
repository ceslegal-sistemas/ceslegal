<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table(config('level-up.user.users_table'), function (Blueprint $table) {
            // Sin ->after('remember_token') - empresas (el "user" de este
            // proyecto) no tiene esa columna, a diferencia de la tabla
            // 'users' que el paquete asume por defecto.
            $table->entityForeignId('level_id')
                ->nullable()
                ->constrained(table: config('level-up.tables.levels'));
        });
    }

    public function down(): void
    {
        Schema::table(config('level-up.user.users_table'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('level_id');
        });
    }
};
