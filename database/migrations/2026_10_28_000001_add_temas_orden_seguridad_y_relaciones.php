<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        DB::table('temas_normativos')->insert([
            [
                'nombre'      => 'Prescripciones de orden, seguridad y videovigilancia',
                'descripcion' => 'Control de acceso, cámaras y videovigilancia, requisas, manejo de dinero y valores, normas de conducta operativa propias del lugar de trabajo.',
                'activo'      => true,
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            [
                'nombre'      => 'Relaciones sentimentales en el trabajo',
                'descripcion' => 'Declaración de relaciones de pareja entre compañeros o con superiores, manejo de conflictos de interés y de jerarquía derivados.',
                'activo'      => true,
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('temas_normativos')->whereIn('nombre', [
            'Prescripciones de orden, seguridad y videovigilancia',
            'Relaciones sentimentales en el trabajo',
        ])->delete();
    }
};
