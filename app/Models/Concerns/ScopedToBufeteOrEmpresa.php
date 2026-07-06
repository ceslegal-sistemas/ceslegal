<?php

namespace App\Models\Concerns;

use App\Support\EmpresaActiva;
use Illuminate\Database\Eloquent\Builder;

trait ScopedToBufeteOrEmpresa
{
    public static function bootScopedToBufeteOrEmpresa(): void
    {
        static::addGlobalScope('bufeteOrEmpresa', function (Builder $q) {
            $user = auth()->user();
            if (! $user) {
                return; // consola / sin auth: sin filtro
            }

            $model = $q->getModel();
            $col = $model instanceof \App\Models\Empresa
                ? $model->getQualifiedKeyName()
                : $model->qualifyColumn('empresa_id');

            if ($user->role === 'super_admin') {
                return; // ve todo
            }

            if ($user->role === 'abogado' && $user->bufete_id === null) {
                return; // staff interno sin bufete: ve todo
            }

            if ($user->role === 'cliente') {
                $q->where($col, $user->empresa_id);
                return;
            }

            if ($user->role === 'abogado' && $user->bufete_id) {
                $ids = \App\Models\Empresa::query()
                    ->withoutGlobalScope('bufeteOrEmpresa')
                    ->where('bufete_id', $user->bufete_id)
                    ->pluck('id')
                    ->all();

                if ($activa = EmpresaActiva::id()) {
                    $ids = in_array($activa, $ids, true) ? [$activa] : $ids;
                }

                $q->whereIn($col, $ids ?: [0]);
                return;
            }

            $q->whereRaw('1 = 0'); // rol desconocido: nada
        });
    }
}
