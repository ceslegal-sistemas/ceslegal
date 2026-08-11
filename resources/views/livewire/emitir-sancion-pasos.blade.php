<div>
    @if ($paso === 1)
        <div>
            <p>PASO 1: Revisar el caso (contenido real en Task 4)</p>
            <button type="button" wire:click="acknowledgeRisk">Marcar riesgo revisado (prueba)</button>
            <button
                type="button"
                wire:click="irAPaso2"
                @disabled(! $riskAcknowledged)
            >
                Continuar a Decisión &rarr;
            </button>
        </div>
    @elseif ($paso === 2)
        <div>
            <p>PASO 2: Decidir la sanción (contenido real en Task 5)</p>
            <button type="button" wire:click="selectDecision('llamado_atencion')">Elegir llamado_atencion (prueba)</button>
            <button
                type="button"
                wire:click="confirmarDecision"
                @disabled(! $decision)
            >
                Continuar a Autorizar &rarr;
            </button>
        </div>
    @endif
</div>
