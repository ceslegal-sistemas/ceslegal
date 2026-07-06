<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Invitación de un bufete a una empresa ya registrada para gestionarla.
 * Regla dura: una empresa pertenece a lo sumo a un bufete (exclusividad).
 */
class BufeteInvitacion extends Model
{
    protected $table = 'bufete_invitaciones';

    protected $fillable = ['bufete_id', 'nit', 'email', 'token', 'estado', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function bufete(): BelongsTo
    {
        return $this->belongsTo(Bufete::class);
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('estado', 'pendiente');
    }

    /** La empresa objetivo (buscada por NIT, sin el scope de acceso). */
    public function empresa(): ?Empresa
    {
        return Empresa::withoutGlobalScope('bufeteOrEmpresa')->where('nit', $this->nit)->first();
    }

    public function estaVigente(): bool
    {
        return $this->estado === 'pendiente'
            && (is_null($this->expires_at) || $this->expires_at->isFuture());
    }

    /**
     * Crea una invitación para una empresa registrada por su NIT.
     * @throws \RuntimeException si la empresa no existe o ya pertenece a un bufete.
     */
    public static function crearPara(Bufete $bufete, string $nit): self
    {
        $empresa = Empresa::withoutGlobalScope('bufeteOrEmpresa')->where('nit', $nit)->first();

        if (! $empresa) {
            throw new \RuntimeException('No existe una empresa registrada con ese NIT.');
        }
        if ($empresa->bufete_id !== null) {
            throw new \RuntimeException('Esa empresa ya pertenece a un bufete.');
        }

        return static::create([
            'bufete_id'  => $bufete->id,
            'nit'        => $nit,
            'email'      => $empresa->email_contacto,
            'token'      => Str::random(40),
            'estado'     => 'pendiente',
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Acepta la invitación: vincula la empresa al bufete si sigue vigente y libre.
     * Devuelve true si vinculó; marca 'expirada' si el plazo ya pasó.
     */
    public function aceptar(): bool
    {
        if (! $this->estaVigente()) {
            if ($this->estado === 'pendiente' && $this->expires_at?->isPast()) {
                $this->update(['estado' => 'expirada']);
            }
            return false;
        }

        $empresa = $this->empresa();
        if (! $empresa || $empresa->bufete_id !== null) {
            return false;
        }

        $empresa->update(['bufete_id' => $this->bufete_id]);
        $this->update(['estado' => 'aceptada']);

        return true;
    }
}
