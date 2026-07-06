<?php

namespace Tests\Feature\Bufete;

use App\Models\Bufete;
use App\Models\BufeteInvitacion;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitacionEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_invitacion_para_empresa_sin_bufete(): void
    {
        $bufete = Bufete::factory()->create();
        $empresa = Empresa::factory()->create(['nit' => '900123456-7', 'bufete_id' => null]);

        $inv = BufeteInvitacion::crearPara($bufete, '900123456-7');

        $this->assertSame('pendiente', $inv->estado);
        $this->assertNotEmpty($inv->token);
        $this->assertTrue($inv->expires_at->isFuture());
        $this->assertDatabaseHas('bufete_invitaciones', ['bufete_id' => $bufete->id, 'nit' => '900123456-7']);
    }

    public function test_aceptar_invitacion_vincula_empresa(): void
    {
        $bufete = Bufete::factory()->create();
        $empresa = Empresa::factory()->create(['nit' => '900222333-1', 'bufete_id' => null]);
        $inv = BufeteInvitacion::crearPara($bufete, '900222333-1');

        $this->assertTrue($inv->aceptar());

        $this->assertSame('aceptada', $inv->fresh()->estado);
        $this->assertSame(
            $bufete->id,
            Empresa::withoutGlobalScope('bufeteOrEmpresa')->find($empresa->id)->bufete_id
        );
    }

    public function test_exclusividad_rechaza_empresa_con_bufete(): void
    {
        $bufeteA = Bufete::factory()->create();
        $bufeteB = Bufete::factory()->create();
        Empresa::factory()->create(['nit' => '900333444-2', 'bufete_id' => $bufeteA->id]);

        $this->expectException(\RuntimeException::class);
        BufeteInvitacion::crearPara($bufeteB, '900333444-2');

        $this->assertDatabaseCount('bufete_invitaciones', 0);
    }

    public function test_token_expirado_no_vincula(): void
    {
        $bufete = Bufete::factory()->create();
        $empresa = Empresa::factory()->create(['nit' => '900555666-3', 'bufete_id' => null]);
        $inv = BufeteInvitacion::crearPara($bufete, '900555666-3');
        $inv->update(['expires_at' => now()->subDay()]);

        $this->assertFalse($inv->aceptar());
        $this->assertNull(Empresa::withoutGlobalScope('bufeteOrEmpresa')->find($empresa->id)->bufete_id);
        $this->assertSame('expirada', $inv->fresh()->estado);
    }
}
