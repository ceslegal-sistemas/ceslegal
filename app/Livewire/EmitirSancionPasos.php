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

    public function irAPaso2(): void
    {
        if (! $this->riskAcknowledged) {
            return;
        }
        // Guarda del lado del servidor: el @disabled del botón es solo UX, un
        // cliente malicioso podría llamar irAPaso2 directo por la red mientras
        // la revisión V6 sigue en curso. Nunca se avanza mientras esté
        // pendiente/procesando (mismo criterio que bloquea "Continuar" en el
        // ->action() de la acción emitir_sancion).
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
        // La validación de exoneración (si $decision no está entre las
        // recomendadas por la IA) se agrega en la Task 6, una vez el
        // componente reciba esa lista.
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
