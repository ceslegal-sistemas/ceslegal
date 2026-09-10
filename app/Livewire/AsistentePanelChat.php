<?php

namespace App\Livewire;

use App\Services\AsistentePanelService;
use Illuminate\Support\Str;
use Livewire\Component;

class AsistentePanelChat extends Component
{
    public string $conversationId = '';

    public array $mensajes = [];

    public string $mensajeActual = '';

    public bool $enviando = false;

    public function mount(): void
    {
        $this->conversationId = (string) Str::uuid();
    }

    public function enviar(AsistentePanelService $servicio): void
    {
        $mensaje = trim($this->mensajeActual);

        if ($mensaje === '') {
            return;
        }

        $this->mensajes[] = ['rol' => 'usuario', 'texto' => $mensaje];
        $this->mensajeActual = '';
        $this->enviando = true;

        $respuesta = $servicio->responder(auth()->user(), $this->conversationId, $mensaje);

        $this->mensajes[] = ['rol' => 'asistente', 'texto' => $respuesta];
        $this->enviando = false;
    }

    public function render()
    {
        return view('livewire.asistente-panel-chat');
    }
}
