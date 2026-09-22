<?php

namespace App\Livewire;

use App\Models\Empresa;
use App\Models\ReglamentoInterno;
use App\Models\Trabajador;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

class SocializacionRit extends Component
{
    public Empresa $empresa;

    public string $etapa = 'documento';

    public string $tipoDocumento = 'CC';
    public string $numeroDocumento = '';

    public ?int $trabajadorId = null;
    public bool $trabajadorExistente = false;

    public string $nombres = '';
    public string $apellidos = '';
    public string $genero = '';
    public string $cargo = '';
    public string $email = '';
    public string $telefono = '';
    public string $direccion = '';

    public bool $esPrimeraAceptacion = true;
    public array $cambiosRit = [];
    public string $ritActivoTextoCompleto = '';
    public array $temasRit = [];

    public bool $declaracionAceptada = false;

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
        // CC/CE/TI son siempre numéricos; pasaporte sí puede ser alfanumérico.
        $reglaNumero = $this->tipoDocumento === 'PASS'
            ? 'required|string|max:15'
            : 'required|digits_between:1,15';

        $this->validate([
            'tipoDocumento' => 'required|in:CC,CE,TI,PASS',
            'numeroDocumento' => $reglaNumero,
        ]);

        $trabajador = Trabajador::withoutGlobalScope('bufeteOrEmpresa')
            ->where('empresa_id', $this->empresa->id)
            ->where('tipo_documento', $this->tipoDocumento)
            ->where('numero_documento', $this->numeroDocumento)
            ->first();

        if ($trabajador) {
            $this->trabajadorId = $trabajador->id;
            $this->trabajadorExistente = true;
            $this->nombres = $trabajador->nombres;
            $this->apellidos = $trabajador->apellidos;
            $this->genero = $trabajador->genero;
            $this->cargo = $trabajador->cargo;
            $this->email = (string) $trabajador->email;
            $this->telefono = (string) $trabajador->telefono;
            $this->direccion = (string) $trabajador->direccion;

            if ($trabajador->aceptoRitVigente()) {
                $this->etapa = 'ya_acepto';
                return;
            }
        }

        $this->etapa = 'datos';
    }

    public function guardarDatos(): void
    {
        $this->validate([
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'genero' => 'required|string',
            'cargo' => 'required|string|max:255',
            'email' => 'required|email',
            'telefono' => 'required|string|max:50',
            'direccion' => 'nullable|string',
        ]);

        $trabajador = Trabajador::withoutGlobalScope('bufeteOrEmpresa')->updateOrCreate(
            [
                'empresa_id' => $this->empresa->id,
                'tipo_documento' => $this->tipoDocumento,
                'numero_documento' => $this->numeroDocumento,
            ],
            [
                'nombres' => $this->nombres,
                'apellidos' => $this->apellidos,
                'genero' => $this->genero,
                'cargo' => $this->cargo,
                'email' => $this->email ?: null,
                'telefono' => $this->telefono ?: null,
                'direccion' => $this->direccion ?: null,
                'active' => true,
            ]
        );

        $this->trabajadorId = $trabajador->id;
        $this->etapa = 'foto';
    }

    public function guardarFotoSimple(string $fotoBase64): void
    {
        $trabajador = Trabajador::withoutGlobalScope('bufeteOrEmpresa')->findOrFail($this->trabajadorId);

        // Nunca sobrescribir una foto de referencia ya existente (podria
        // haber sido tomada antes con mejor calidad, ej. subida manual por
        // el admin) - solo se llena si estaba vacia.
        if (!$trabajador->foto_referencia_path) {
            [, $datos] = explode(',', $fotoBase64, 2);
            $contenido = base64_decode($datos);
            $ruta = 'fotos_referencia/' . $trabajador->id . '_' . Str::random(8) . '.jpg';
            Storage::disk('local')->put($ruta, $contenido);

            $trabajador->update(['foto_referencia_path' => $ruta]);
        }

        $trabajadorObj = Trabajador::withoutGlobalScope('bufeteOrEmpresa')->find($this->trabajadorId);
        $ritActivo = $this->resolverRitActivo(); // NUNCA $this->empresa->reglamentoInterno - ver Gotcha crítico #3
        $ultimaAceptacion = $trabajadorObj?->aceptacionesReglamentoInterno()
            ->latest('aceptado_en')
            ->first();

        $this->ritActivoTextoCompleto = (string) $ritActivo->texto_completo;
        // Legal Design: nadie lee el reglamento completo en este paso - se
        // muestran los temas que ya tiene clasificados (taxonomia fija de 27
        // temas, sin llamada nueva a IA) como resumen simple, con el texto
        // completo disponible aparte para quien SI quiera leerlo entero.
        $this->temasRit = $ritActivo->temasNormativos()
            ->activos()
            ->get(['temas_normativos.nombre', 'temas_normativos.descripcion'])
            ->map(fn ($tema) => ['nombre' => $tema->nombre, 'descripcion' => $tema->descripcion])
            ->all();

        if ($ultimaAceptacion) {
            $this->esPrimeraAceptacion = false;

            // $ultimaAceptacion->reglamentoInterno (belongsTo) dispararía
            // OTRA consulta sin proteger contra ReglamentoInterno::
            // ScopedToBufeteOrEmpresa - mismo riesgo del Gotcha crítico #3,
            // esta vez en una relación belongsTo en vez de hasOne. Siempre
            // resolver por id con withoutGlobalScope explícito, nunca vía
            // la relación directa, en NINGÚN modelo que use ese scope.
            $versionAnterior = ReglamentoInterno::withoutGlobalScope('bufeteOrEmpresa')
                ->find($ultimaAceptacion->reglamento_interno_id);

            $this->cambiosRit = app(\App\Services\RitDiffService::class)->compararDocumentos(
                (string) $versionAnterior->texto_completo,
                $this->ritActivoTextoCompleto
            );
        } else {
            $this->esPrimeraAceptacion = true;
        }

        $this->etapa = 'presentacion_rit';
    }

    public function aceptarReglamento(): void
    {
        $this->validate([
            'declaracionAceptada' => 'accepted',
        ]);

        $ritActivo = $this->resolverRitActivo(); // NUNCA $this->empresa->reglamentoInterno - ver Gotcha crítico #3

        \App\Models\AceptacionReglamentoInterno::updateOrCreate(
            [
                'trabajador_id' => $this->trabajadorId,
                'reglamento_interno_id' => $ritActivo->id,
            ],
            [
                'aceptado_en' => now(),
                'ip_aceptacion' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ]
        );

        $this->etapa = 'completado';
    }

    public function render()
    {
        return view('livewire.socializacion-rit');
    }
}
