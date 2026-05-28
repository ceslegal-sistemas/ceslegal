<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('google_oauth_email', 255)->nullable()->after('email_contacto');
            $table->text('google_oauth_tokens')->nullable()->after('google_oauth_email');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['google_oauth_email', 'google_oauth_tokens']);
        });
    }
};
