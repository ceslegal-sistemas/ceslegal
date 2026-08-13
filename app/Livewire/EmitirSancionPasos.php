<?php

namespace App\Livewire;

use App\Models\SancionProcessEvent;
use Livewire\Component;

class EmitirSancionPasos extends Component
{
    public int $procesoId;
    public int $paso = 1;
    public bool $riskAcknowledged = false;
    public ?string $decision = null;
    public string $razonDivergencia = '';
    public bool $exoneracionAceptada = false;

    public array $analisis = [];
    public bool $esFallback = false;
    public array $opcionesSancion = [];
    public array $iaSancionesRecomendadas = [];
    public ?array $recomendacionFinal = null;
    public array $autoridadRit = [];
    public array $iaRazonesNoRecomendadas = [];
    public ?string $validacionesV6Estado = null;
    public ?array $validacionesV6Resultados = null;
    public ?array $validacionesV6PuntosClave = null;
    public ?\Illuminate\Support\Carbon $validacionesV6En = null;

    public function mount(
        int $procesoId,
        array $analisis = [],
        bool $esFallback = false,
        array $opcionesSancion = [],
        array $iaSancionesRecomendadas = [],
        ?array $recomendacionFinal = null,
        array $autoridadRit = [],
        array $iaRazonesNoRecomendadas = [],
        ?string $validacionesV6Estado = null,
        ?array $validacionesV6Resultados = null,
        ?array $validacionesV6PuntosClave = null,
        ?\Illuminate\Support\Carbon $validacionesV6En = null,
        ?string $decision = null,
    ): void {
        $this->procesoId = $procesoId;
        $this->analisis = $analisis;
        $this->esFallback = $esFallback;
        $this->opcionesSancion = $opcionesSancion;
        $this->iaSancionesRecomendadas = $iaSancionesRecomendadas;
        $this->recomendacionFinal = $recomendacionFinal;
        $this->autoridadRit = $autoridadRit;
        $this->iaRazonesNoRecomendadas = $iaRazonesNoRecomendadas;
        $this->validacionesV6Estado = $validacionesV6Estado;
        $this->validacionesV6Resultados = $validacionesV6Resultados;
        $this->validacionesV6PuntosClave = $validacionesV6PuntosClave;
        $this->validacionesV6En = $validacionesV6En;

        // Al hacer clic en "Volver" desde Verificación del Autorizador, el
        // padre oculta este wizard (paso_actual<3) y vuelve a mostrarlo -
        // como Filament ya no lo tenía en el árbol, este componente se
        // remonta desde cero (pierde su $paso/$decision anteriores). Si el
        // padre ya tenía guardada una decisión (tipo_sancion), se restaura
        // aquí y se abre directo en el Paso 2, en vez de mandar al cliente
        // otra vez al Paso 1 como si nada se hubiera elegido.
        if ($decision) {
            $this->decision = $decision;
            $this->paso = 2;
        }
    }

    // No bloquea el avance del wizard (ver irAPaso2) - solo deja registro en
    // sancion_process_events de que el usuario abrió el punto "Resistencia
    // ante una revisión judicial" de la revisión de calidad V6, para el
    // historial de auditoría del proceso.
    public function acknowledgeRisk(): void
    {
        if ($this->riskAcknowledged) {
            return;
        }
        $this->riskAcknowledged = true;
        $this->logEvent('risk_acknowledged');
    }

    public function selectDecision(string $tipo): void
    {
        $this->decision = $tipo;
        $this->logEvent('decision_selected', ['tipo_sancion' => $tipo]);
    }

    public function irAPaso1(): void
    {
        // Guarda del lado del servidor: el @disabled del botón es solo UX, un
        // cliente malicioso podría llamar irAPaso1 directo por la red mientras
        // la revisión V6 sigue en curso. Nunca se avanza mientras esté
        // pendiente/procesando (mismo criterio que bloquea "Continuar" en el
        // ->action() de la acción emitir_sancion).
        if (in_array($this->validacionesV6Estado, ['pendiente', 'procesando'], true)) {
            return;
        }
        $this->paso = 1;
    }
    public function irAPaso2(): void
    {
        // Guarda del lado del servidor: el @disabled del botón es solo UX, un
        // cliente malicioso podría llamar irAPaso2 directo por la red mientras
        // la revisión V6 sigue en curso. Nunca se avanza mientras esté
        // pendiente/procesando (mismo criterio que bloquea "Continuar" en el
        // ->action() de la acción emitir_sancion). La revisión de calidad V6
        // en sí (motores, incluido el de riesgo) es solo informativa - no se
        // exige abrirla ni reconocerla para avanzar, ver acknowledgeRisk().
        if (in_array($this->validacionesV6Estado, ['pendiente', 'procesando'], true)) {
            return;
        }
        $this->paso = 2;
    }

    public function confirmarDecision(): void
    {
        if (! $this->decision) {
            return;
        }
        // Guarda del lado del servidor (mismo criterio que irAPaso2): el
        // @disabled del botón "Continuar a Autorizar" es solo UX, un cliente
        // malicioso podría llamar confirmarDecision directo por la red. Si la
        // decisión no está entre las sanciones recomendadas por la IA, exige
        // razón de divergencia (mínimo 5 caracteres) y la exoneración aceptada.
        if (
            ! empty($this->iaSancionesRecomendadas)
            && ! in_array($this->decision, $this->iaSancionesRecomendadas, true)
            && (strlen(trim($this->razonDivergencia)) < 5 || ! $this->exoneracionAceptada)
        ) {
            return;
        }
        $this->dispatch('emitir-sancion-paso2-completo',
            tipoSancion: $this->decision,
            razonDivergencia: $this->razonDivergencia,
            exoneracionAceptada: $this->exoneracionAceptada,
        );
    }

    protected function logEvent(string $tipo, array $meta = []): void
    {
        SancionProcessEvent::create([
            'proceso_id' => $this->procesoId,
            'user_id' => auth()->id(),
            'event_type' => $tipo,
            'meta' => $meta ?: null,
        ]);
    }

    public function render()
    {
        return view('livewire.emitir-sancion-pasos');
    }
}
