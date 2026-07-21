<?php

namespace App\Livewire;

use App\Support\EmpresaActiva;
use Livewire\Component;

/**
 * Selector de "empresa activa" para un abogado de bufete. Persiste la elección en
 * sesión (EmpresaActiva); el global scope ScopedToBufeteOrEmpresa acota los datos a
 * esa empresa (o a todas las del bufete si se elige "Todas").
 */
class SelectorEmpresa extends Component
{
    /** Empresas que el usuario puede seleccionar (solo las de su bufete). */
    private function permitidas(): array
    {
        $user = auth()->user();
        if (! $user || ! $user->esAbogadoDeBufete()) {
            return [];
        }

        return $user->empresasGestionadas()->paraAsignar()->pluck('id')->all();
    }

    public function seleccionar($id): void
    {
        $id = (int) $id;
        if (in_array($id, $this->permitidas(), true)) {
            EmpresaActiva::set($id);
            $this->js('window.location.reload()');
        }
    }

    public function todas(): void
    {
        EmpresaActiva::clear();
        $this->js('window.location.reload()');
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.selector-empresa', [
            'empresas' => ($user && $user->esAbogadoDeBufete())
                ? $user->empresasGestionadas()->paraAsignar()->orderBy('razon_social')->get(['id', 'razon_social'])
                : collect(),
            'activaId' => EmpresaActiva::id(),
        ]);
    }
}
