<?php

namespace App\Livewire;

use App\Models\Empresa;
use App\Support\EmpresaActiva;
use Livewire\Component;

/**
 * Selector de "empresa activa" para el topbar del panel - un abogado de
 * bufete ve solo las empresas de SU bufete; super_admin/abogado interno
 * (staff de LUPE Legal) ve TODAS las empresas del sistema, con buscador
 * (pedido explícito del usuario, 2026-09-29: sin esto, super_admin no
 * tenía ninguna forma de elegir empresa y "Mi Reglamento Interno" caía
 * silenciosamente a Empresa::first()).
 */
class SelectorEmpresa extends Component
{
    public string $busqueda = '';

    /** ¿Puede este usuario usar el selector? (staff interno o abogado de bufete). */
    private function puedeSeleccionar(): bool
    {
        $user = auth()->user();

        return $user && ($user->esAbogadoDeBufete() || $user->hasRole('super_admin') || $user->hasRole('abogado'));
    }

    /** IDs de las empresas que el usuario puede seleccionar. */
    private function permitidas(): array
    {
        return $this->empresasElegibles()->pluck('id')->all();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Empresa> */
    private function empresasElegibles()
    {
        $user = auth()->user();

        if (! $user) {
            return Empresa::query()->whereRaw('1 = 0')->get();
        }

        $query = $user->esAbogadoDeBufete()
            ? $user->empresasGestionadas()->paraAsignar()
            : Empresa::query()->paraAsignar();

        if ($this->busqueda !== '') {
            $query->where('razon_social', 'like', '%' . $this->busqueda . '%');
        }

        return $query->orderBy('razon_social')->get(['id', 'razon_social']);
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
        $activaId = EmpresaActiva::id();

        return view('livewire.selector-empresa', [
            'empresas' => $this->puedeSeleccionar() ? $this->empresasElegibles() : collect(),
            'activaId' => $activaId,
            'nombreActiva' => $activaId ? Empresa::find($activaId)?->razon_social : null,
        ]);
    }
}
