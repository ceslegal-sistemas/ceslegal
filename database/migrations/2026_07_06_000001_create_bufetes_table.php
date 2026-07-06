<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bufetes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('nit')->nullable()->unique();
            $table->string('representante')->nullable();
            $table->string('email_contacto')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bufetes');
    }
};
