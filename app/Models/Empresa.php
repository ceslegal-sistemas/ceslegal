<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

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
}
