<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->json('video_didactico_capitulos')->nullable()->after('video_didactico_path');
        });
    }

    public function down(): void
    {
        Schema::table('reglamentos_internos', function (Blueprint $table) {
            $table->dropColumn('video_didactico_capitulos');
        });
    }
};
