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
        Schema::create('timeline', function (Blueprint $table) {
            $table->id();
            $table->enum('proceso_tipo', ['proceso_disciplinario', 'contrato']);
            $table->unsignedBigInteger('proceso_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('accion', 255);
            $table->text('descripcion')->nullable();
            $table->string('estado_anterior', 100)->nullable();
            $table->string('estado_nuevo', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['proceso_tipo', 'proceso_id'], 'idx_proceso');
            $table->index('user_id', 'idx_user');
            $table->index('created_at', 'idx_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timeline');
    }
};
