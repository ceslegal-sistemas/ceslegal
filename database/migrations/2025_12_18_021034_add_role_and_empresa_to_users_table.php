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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'abogado', 'cliente', 'rrhh'])->default('abogado')->after('password');
            $table->foreignId('empresa_id')->nullable()->after('role')->constrained('empresas')->onDelete('restrict');
            $table->boolean('active')->default(true)->after('empresa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropColumn(['role', 'empresa_id', 'active']);
        });
    }
};
