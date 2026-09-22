<?php

namespace App\Livewire;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Livewire\Component;

class SocializacionRit extends Component
{
    public Empresa $empresa;

    public string $etapa = 'documento';

    public string $tipoDocumento = 'CC';
    public string $numeroDocumento = '';

    public ?int $trabajadorId = null;
    public bool $trabajadorExistente = false;

    public function mount(Empresa $empresa): void
    {
        $this->empresa = $empresa;

        if (!$this->resolverRitActivo()) {
            $this->etapa = 'sin_rit';
        }
    }

    /**
     * Resuelve el RIT activo de $this->empresa SIEMPRE con
     * withoutGlobalScope('bufeteOrEmpresa') - ReglamentoInterno usa el mismo
     * scope que Empresa/Trabajador (ScopedToBufeteOrEmpresa). Sin esto, si
     * un usuario de bufete tiene otra "empresa activa" seleccionada en el
     * topbar en el mismo navegador donde alguien abre este link público, la
     * relación `$empresa->reglamentoInterno` puede devolver null en vez del
     * RIT real. Nunca usar esa relación directamente en NINGÚN punto de
     * este componente - siempre pasar por este método.
     */
    private function resolverRitActivo(): ?ReglamentoInterno
    {
        return ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')
            ->where('empresa_id', $this->empresa->id)
            ->where('activo', true)
            ->latest('updated_at')
            ->first();
    }

    /**
     * Busca al trabajador SIEMPRE dentro de $this->empresa (resuelta por
     * token en el controlador) - el empresa_id nunca sale de un campo del
     * formulario, para no cruzar trabajadores entre empresas distintas.
     * withoutGlobalScope: mismo motivo que en SocializacionRitPublicoController.
     */
    public function buscarTrabajador(): void
    {
        $this->validate([
            'tipoDocumento' => 'required|in:CC,CE,TI,PASS',
            'numeroDocumento' => 'required|string|max:50',
        ]);

        $trabajador = Trabajador::withoutGlobalScope('bufeteOrEmpresa')
            ->where('empresa_id', $this->empresa->id)
            ->where('tipo_documento', $this->tipoDocumento)
            ->where('numero_documento', $this->numeroDocumento)
            ->first();

        if ($trabajador) {
            $this->trabajadorId = $trabajador->id;
            $this->trabajadorExistente = true;

            if ($trabajador->aceptoRitVigente()) {
                $this->etapa = 'ya_acepto';
                return;
            }
        }

        $this->etapa = 'datos';
    }

    public function render()
    {
        return view('livewire.socializacion-rit');
    }
}
