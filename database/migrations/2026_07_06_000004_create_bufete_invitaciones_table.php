<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bufete_invitaciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bufete_id')->constrained('bufetes')->cascadeOnDelete();
            $t->string('nit')->nullable();
            $t->string('email')->nullable();
            $t->string('token')->unique();
            $t->string('estado')->default('pendiente'); // pendiente | aceptada | rechazada | expirada
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bufete_invitaciones');
    }
};
