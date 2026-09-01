<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Models\AuditoriaRIT;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MiReglamentoInternoAuditoriaVigenteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug real reportado por el usuario con captura de pantalla: construyó un
     * RIT completamente nuevo desde cero con el wizard "Construir RIT" (que
     * desactiva el RIT viejo, ver ReglamentoInternoUnicoActivoTest) y, al
     * terminar, "Mi Reglamento Interno" seguía mostrando el aviso "Reglamento
     * actualizado con IA... Esta es su versión vigente" de una auditoría y
     * mejora VIEJAS, adoptadas semanas atrás sobre el RIT ya reemplazado.
     * Causa: mount() tomaba "la auditoría más reciente de la empresa" sin
     * filtrar por el RIT que audita - con el RIT nuevo aún sin auditar, la
     * auditoría vieja (ligada al RIT ya inactivo) seguía siendo "la más
     * reciente" y su banner de mejora adoptada se mostraba igual.
     */
    public function test_no_muestra_la_mejora_adoptada_de_un_rit_ya_reemplazado(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);

        $ritViejo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => false,
            'fuente' => 'subido',
            'texto_completo' => 'RIT viejo, ya reemplazado.',
        ]);
        $ritMejoradoViejo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => false,
            'fuente' => 'mejora_ia',
            'reglamento_origen_id' => $ritViejo->id,
            'texto_completo' => 'RIT mejorado del ciclo anterior, ya reemplazado.',
        ]);
        AuditoriaRIT::create([
            'empresa_id' => $empresa->id,
            'reglamento_interno_id' => $ritViejo->id,
            'estado' => 'completado',
            'estado_mejora' => 'completado',
            'reglamento_mejorado_id' => $ritMejoradoViejo->id,
            'decision_mejora' => 'adoptado',
            'secciones' => [['titulo' => 'x', 'score' => 60]],
        ]);

        $ritNuevo = ReglamentoInterno::create([
            'empresa_id' => $empresa->id,
            'activo' => true,
            'fuente' => 'construido_ia',
            'texto_completo' => 'RIT nuevo, construido desde cero con el wizard.',
            'estado_generacion' => 'completado',
        ]);

        $user = User::factory()->create(['role' => 'cliente', 'empresa_id' => $empresa->id, 'active' => true]);

        $component = Livewire::actingAs($user)->test(MiReglamentoInterno::class);

        $this->assertTrue($component->get('reglamento')->is($ritNuevo));
        $this->assertNull($component->get('auditoria'));
    }
}
