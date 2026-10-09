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
     * Variables (marcadores) que admite cada texto: [clave => [marcador => qué es]].
     * Es lo que muestra el formulario de edición (botones para insertarlas y
     * vista previa). Tiene que coincidir con lo que realmente reemplaza el
     * código que muestra cada texto - si se agrega un marcador allá, se agrega aquí.
     */
    public const VARIABLES = [
        'disclaimer_descargos' => [
            ':nombre' => 'Nombre completo del trabajador',
            ':cedula' => 'Número de documento del trabajador',
            ':empresa' => 'Razón social de la empresa',
            ':cargo' => 'Cargo del trabajador',
        ],
        'aviso_no_comprendio_primera_vez' => [
            ':empresa' => 'Razón social de la empresa',
        ],
        'aviso_no_comprendio_confirmacion' => [
            ':empresa' => 'Razón social de la empresa',
        ],
        'mensaje_no_comprendio_bloqueado' => [
            ':trabajador' => 'Nombre completo del trabajador',
            ':empresa' => 'Razón social de la empresa',
            ':fecha' => 'Fecha de hoy, cuando se le entregó el video y el documento (ej. 8 de octubre de 2026)',
        ],
    ];

    /** Datos de ejemplo para la vista previa del formulario de edición. */
    public const EJEMPLOS = [
        ':nombre' => 'Juan Pérez Gómez',
        ':trabajador' => 'Juan Pérez Gómez',
        ':cedula' => '1.234.567.890',
        ':empresa' => 'RENBEL S.A.S.',
        ':cargo' => 'Auxiliar administrativo',
        ':fecha' => '8 de octubre de 2026',
    ];

    /** @return array<string, string> marcador => descripción (vacío si el texto no admite variables) */
    public static function variablesDe(?string $clave): array
    {
        return self::VARIABLES[$clave] ?? [];
    }

    /**
     * Obtiene el valor de una clave con fallback opcional.
     */
    public static function obtener(string $clave, string $fallback = ''): string
    {
        return static::where('clave', $clave)->value('valor') ?? $fallback;
    }
}
