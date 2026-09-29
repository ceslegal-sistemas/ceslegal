<?php

namespace Tests\Feature;

use App\Jobs\GenerarVideoDidacticoRITJob;
use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\TemaNormativo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerarVideoDidacticoRITJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_handle_genera_el_video_y_actualiza_el_rit(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'id' => 'v1_test',
                'status' => 'completed',
                'steps' => [['type' => 'model_output', 'content' => [['type' => 'video', 'data' => 'ZmFrZQ==']]]],
            ], 200),
        ]);

        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'video_didactico_estado' => 'generando',
        ]);
        $tema = TemaNormativo::create(['nombre' => 'Jornada laboral', 'descripcion' => 'Horarios.', 'activo' => true]);
        $rit->temasNormativos()->attach($tema->id, ['resumen_simple' => 'x']);
        $user = User::factory()->create();

        (new GenerarVideoDidacticoRITJob($rit, $user->id))->handle(app(\App\Services\RitVideoDidacticoService::class));

        $rit->refresh();
        $this->assertSame('completado', $rit->video_didactico_estado);
        $this->assertNotNull($rit->video_didactico_path);
    }

    public function test_failed_marca_el_rit_con_error_y_notifica(): void
    {
        $empresa = Empresa::factory()->create(['active' => true]);
        $rit = ReglamentoInterno::create([
            'empresa_id' => $empresa->id, 'activo' => true, 'fuente' => 'construido_ia', 'texto_completo' => 'v1',
            'video_didactico_estado' => 'generando',
        ]);
        $user = User::factory()->create();

        $job = new GenerarVideoDidacticoRITJob($rit, $user->id);
        $job->failed(new \RuntimeException('cuota agotada'));

        $rit->refresh();
        $this->assertSame('error', $rit->video_didactico_estado);
        $this->assertSame('cuota agotada', $rit->video_didactico_error);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
    }
}
