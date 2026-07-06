<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bufete extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'nit',
        'representante',
        'email_contacto',
        'telefono',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }

    public function abogados(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
