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
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->string('video_didactico_path')->nullable()->after('ruta_pdf');
            $table->string('video_didactico_estado')->nullable()->after('video_didactico_path');
            $table->text('video_didactico_error')->nullable()->after('video_didactico_estado');
            $table->timestamp('video_didactico_generado_en')->nullable()->after('video_didactico_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn([
                'video_didactico_path',
                'video_didactico_estado',
                'video_didactico_error',
                'video_didactico_generado_en',
            ]);
        });
    }
};
