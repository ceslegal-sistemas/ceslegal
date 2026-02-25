<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Empresa extends Model
{
    protected $fillable = [
        'razon_social',
        'nit',
        'direccion',
        'telefono',
        'email_contacto',
        'ciudad',
        'departamento',
        'representante_legal',
        'active',
        'dias_laborales',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Verifica si la empresa trabaja los sábados
     */
    public function trabajaSabados(): bool
    {
        return $this->dias_laborales === 'lunes_sabado';
    }

    /**
     * Obtiene el texto de los días laborales
     */
    public function getDiasLaboralesTextoAttribute(): string
    {
        return match ($this->dias_laborales) {
            'lunes_sabado' => 'Lunes a Sábado',
            default => 'Lunes a Viernes',
        };
    }

    public function trabajadores(): HasMany
    {
        return $this->hasMany(Trabajador::class);
    }

    public function procesosDisciplinarios(): HasMany
    {
        return $this->hasMany(ProcesoDisciplinario::class);
    }

    public function solicitudesContrato(): HasMany
    {
        return $this->hasMany(SolicitudContrato::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function informesJuridicos(): HasMany
    {
        return $this->hasMany(InformeJuridico::class);
    }

    public function reglamentoInterno(): HasOne
    {
        return $this->hasOne(ReglamentoInterno::class)->where('activo', true)->latest();
    }
}
