<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = [
        'clave',
        'valor',
        'tipo',
        'descripcion',
        'categoria',
        'editable',
    ];

    protected $casts = [
        'editable' => 'boolean',
    ];

    public function getValorParsedAttribute()
    {
        return match ($this->tipo) {
            'number' => (int) $this->valor,
            'boolean' => filter_var($this->valor, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->valor, true),
            default => $this->valor,
        };
    }

    public static function obtener($clave, $default = null)
    {
        $config = static::where('clave', $clave)->first();
        return $config ? $config->valor_parsed : $default;
    }
}
