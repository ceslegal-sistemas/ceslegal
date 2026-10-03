<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuraciones_textos')->insert([
            'clave' => 'disclaimer_culminacion_socializacion',
            'grupo' => 'rit',
            'descripcion' => 'Declaración del admin al presionar "Culminar Socialización del RIT" - declara que notificó a todos los trabajadores, que la socialización fue aceptada/realizada, y que conservará las evidencias. Marcador: :empresa.',
            'valor' => config('ces.disclaimer_culminacion_socializacion'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('configuraciones_textos')->where('clave', 'disclaimer_culminacion_socializacion')->delete();
    }
};
