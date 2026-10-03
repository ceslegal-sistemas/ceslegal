<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        DB::table('configuraciones_textos')->insert([
            [
                'clave' => 'aviso_no_comprendio_primera_vez',
                'grupo' => 'rit',
                'descripcion' => 'Aviso suave (no bloquea) la primera vez que el trabajador declara no entender el RIT. Marcador: :empresa.',
                'valor' => config('ces.aviso_no_comprendio_primera_vez'),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'clave' => 'mensaje_no_comprendio_bloqueado',
                'grupo' => 'rit',
                'descripcion' => 'Mensaje final cuando el trabajador insiste por segunda vez en no entender el RIT - bloquea el proceso y se envía a RRHH. Marcador: :empresa.',
                'valor' => config('ces.mensaje_no_comprendio_bloqueado'),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('configuraciones_textos')->whereIn('clave', [
            'aviso_no_comprendio_primera_vez',
            'mensaje_no_comprendio_bloqueado',
        ])->delete();
    }
};
