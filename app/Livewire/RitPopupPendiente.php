<?php

namespace App\Livewire;

use App\Filament\Admin\Pages\MiReglamentoInterno;
use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Popup bloqueante al hacer login (pedido explícito de Andrés Sarmiento,
 * reunión 2026-09-28, action item de Juan Pablo Prendón): "apenas tú te
 * loguees le aparezca... una ventana emergente que le dice bien si hay un
 * nuevo, si está sin socializar o si hay un nuevo reglamento de trabajo
 * para ser actualizado... que no lo deje hacer nada... no puede decir que
 * no lo vio".
 *
 * Montado vía renderHook(BODY_END) en EmpresaPanelProvider, igual que el
 * widget de Chatwoot - solo para role 'cliente' (un bufete/admin maneja
 * muchas empresas, no tiene un solo "mi RIT").
 *
 * Reaparece una vez por sesión de login (decisión explícita del usuario,
 * 2026-10-05): se guarda en la sesión de Laravel (no en BD) para que
 * vuelva a aparecer automáticamente en el siguiente login sin necesitar
 * limpieza manual - la sesión típica se invalida/regenera entre logins.
 */
class RitPopupPendiente extends Component
{
    public bool $mostrar = false;
    public string $fase = 'publicacion';

    public function mount(): void
    {
        $empresa = $this->empresaDelUsuario();
        if (!$empresa) {
            return;
        }

        if (session()->get($this->claveSesion($empresa))) {
            return;
        }

        if (!$empresa->ritPendienteDeSocializar()) {
            return;
        }

        $this->fase = $empresa->reglamentoInterno?->faseSocializacionActual() ?? 'publicacion';
        $this->mostrar = true;
    }

    public function irAMiReglamento()
    {
        $empresa = $this->empresaDelUsuario();
        if ($empresa) {
            session()->put($this->claveSesion($empresa), true);
        }

        return redirect(MiReglamentoInterno::getUrl(panel: 'empresa'));
    }

    private function empresaDelUsuario(): ?Empresa
    {
        $user = Auth::user();

        return $user?->role === 'cliente' ? $user->empresa : null;
    }

    private function claveSesion(Empresa $empresa): string
    {
        return "rit_popup_pendiente_visto_{$empresa->id}";
    }

    public function render()
    {
        return view('livewire.rit-popup-pendiente');
    }
}
