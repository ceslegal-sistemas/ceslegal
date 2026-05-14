<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionTexto extends Model
{
    protected $table = 'configuraciones_textos';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'clave',
        'grupo',
        'descripcion',
        'valor',
    ];

    /**
     * Obtiene el valor de una clave con fallback opcional.
     */
    public static function obtener(string $clave, string $fallback = ''): string
    {
        return static::where('clave', $clave)->value('valor') ?? $fallback;
    }
}
