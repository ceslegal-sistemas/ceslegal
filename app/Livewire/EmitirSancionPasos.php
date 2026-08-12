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
        // El reconocimiento del riesgo solo se exige cuando la revisión V6
        // terminó y realmente produjo un motor de riesgo para reconocer (fila
        // "Resistencia ante una revisión judicial" en validaciones-v6-resumen.blade.php).
        // Si el estado es null/'error' (p.ej. falla de cuota de Gemini) esa fila
        // nunca se renderiza, así que exigir el acknowledge dejaría al usuario
        // bloqueado para siempre sin forma de avanzar.
        if ($this->validacionesV6Estado === 'completado' && ! $this->riskAcknowledged) {
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
        // ->action() de la acción emitir_sancion).
        if (in_array($this->validacionesV6Estado, ['pendiente', 'procesando'], true)) {
            return;
        }
        // El reconocimiento del riesgo solo se exige cuando la revisión V6
        // terminó y realmente produjo un motor de riesgo para reconocer (fila
        // "Resistencia ante una revisión judicial" en validaciones-v6-resumen.blade.php).
        // Si el estado es null/'error' (p.ej. falla de cuota de Gemini) esa fila
        // nunca se renderiza, así que exigir el acknowledge dejaría al usuario
        // bloqueado para siempre sin forma de avanzar.
        if ($this->validacionesV6Estado === 'completado' && ! $this->riskAcknowledged) {
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
