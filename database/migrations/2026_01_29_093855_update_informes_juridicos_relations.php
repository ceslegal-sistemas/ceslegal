<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('informes_juridicos', function (Blueprint $table) {
            // Agregar las nuevas columnas de foreign keys
            $table->foreignId('area_practica_id')->nullable()->after('mes')->constrained('areas_practica')->nullOnDelete();
            $table->foreignId('tipo_gestion_id')->nullable()->after('area_practica_otro')->constrained('tipos_gestion')->nullOnDelete();
            $table->foreignId('subtipo_id')->nullable()->after('tipo_gestion_otro')->constrained('subtipos_gestion')->nullOnDelete();
        });

        // Migrar datos existentes si los hay
        $this->migrateExistingData();

        // Eliminar las columnas antiguas
        Schema::table('informes_juridicos', function (Blueprint $table) {
            $table->dropColumn(['area_practica', 'tipo_gestion', 'subtipo']);
        });
    }

    private function migrateExistingData(): void
    {
        // Mapear áreas de práctica
        $areasMap = [
            'disciplinario' => DB::table('areas_practica')->where('nombre', 'Disciplinario')->value('id'),
            'documental' => DB::table('areas_practica')->where('nombre', 'Documental')->value('id'),
            'acompanamiento' => DB::table('areas_practica')->where('nombre', 'Acompañamiento')->value('id'),
            'contractual' => DB::table('areas_practica')->where('nombre', 'Contractual')->value('id'),
            'societario' => DB::table('areas_practica')->where('nombre', 'Societario')->value('id'),
        ];

        // Mapear tipos de gestión
        $tiposMap = [
            'oficio' => DB::table('tipos_gestion')->where('nombre', 'Oficio')->value('id'),
            'memorando' => DB::table('tipos_gestion')->where('nombre', 'Memorando')->value('id'),
            'contrato' => DB::table('tipos_gestion')->where('nombre', 'Contrato')->value('id'),
            'notificacion' => DB::table('tipos_gestion')->where('nombre', 'Notificación')->value('id'),
            'suspension' => DB::table('tipos_gestion')->where('nombre', 'Suspensión')->value('id'),
            'societario' => DB::table('tipos_gestion')->where('nombre', 'Societario')->value('id'),
            'asesoria' => DB::table('tipos_gestion')->where('nombre', 'Asesoría')->value('id'),
            'poder' => DB::table('tipos_gestion')->where('nombre', 'Poder')->value('id'),
            'acta' => DB::table('tipos_gestion')->where('nombre', 'Acta')->value('id'),
        ];

        // Mapear subtipos
        $subtiposMap = [
            'documento' => DB::table('subtipos_gestion')->where('nombre', 'Documento')->value('id'),
            'especial' => DB::table('subtipos_gestion')->where('nombre', 'Especial')->value('id'),
            'acta_asamblea' => DB::table('subtipos_gestion')->where('nombre', 'Acta de Asamblea')->value('id'),
            'contestacion' => DB::table('subtipos_gestion')->where('nombre', 'Contestación')->value('id'),
            'preaviso' => DB::table('subtipos_gestion')->where('nombre', 'Preaviso')->value('id'),
            'memorial' => DB::table('subtipos_gestion')->where('nombre', 'Memorial')->value('id'),
            'presencial' => DB::table('subtipos_gestion')->where('nombre', 'Presencial')->value('id'),
            'virtual' => DB::table('subtipos_gestion')->where('nombre', 'Virtual')->value('id'),
        ];

        // Actualizar registros existentes
        $informes = DB::table('informes_juridicos')->get();
        foreach ($informes as $informe) {
            $updates = [];

            if (isset($informe->area_practica) && isset($areasMap[$informe->area_practica])) {
                $updates['area_practica_id'] = $areasMap[$informe->area_practica];
            }

            if (isset($informe->tipo_gestion) && isset($tiposMap[$informe->tipo_gestion])) {
                $updates['tipo_gestion_id'] = $tiposMap[$informe->tipo_gestion];
            }

            if (isset($informe->subtipo) && isset($subtiposMap[$informe->subtipo])) {
                $updates['subtipo_id'] = $subtiposMap[$informe->subtipo];
            }

            if (!empty($updates)) {
                DB::table('informes_juridicos')->where('id', $informe->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        Schema::table('informes_juridicos', function (Blueprint $table) {
            // Restaurar columnas antiguas
            $table->string('area_practica', 50)->nullable()->after('mes');
            $table->string('tipo_gestion', 100)->nullable()->after('area_practica_otro');
            $table->string('subtipo', 100)->nullable()->after('tipo_gestion_otro');
        });

        Schema::table('informes_juridicos', function (Blueprint $table) {
            // Eliminar foreign keys
            $table->dropConstrainedForeignId('area_practica_id');
            $table->dropConstrainedForeignId('tipo_gestion_id');
            $table->dropConstrainedForeignId('subtipo_id');
        });
    }
};
