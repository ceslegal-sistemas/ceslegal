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
