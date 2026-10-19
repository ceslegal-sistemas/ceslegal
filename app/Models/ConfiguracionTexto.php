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
            ':nombre' => 'Nombre del trabajador',
            ':cedula' => 'Cédula del trabajador',
            ':empresa' => 'Nombre de la empresa',
            ':cargo' => 'Cargo del trabajador',
            ':url_politica' => 'Enlace a la Política de Datos',
            ':fecha' => 'Fecha de hoy',
        ],
        'aviso_no_comprendio_primera_vez' => [
            ':empresa' => 'Nombre de la empresa',
            ':trabajador' => 'Nombre del trabajador',
        ],
        'aviso_no_comprendio_confirmacion' => [
            ':empresa' => 'Nombre de la empresa',
            ':trabajador' => 'Nombre del trabajador',
        ],
        'mensaje_no_comprendio_bloqueado' => [
            ':trabajador' => 'Nombre del trabajador',
            ':empresa' => 'Nombre de la empresa',
            ':fecha' => 'Fecha de hoy',
        ],
        'disclaimer_autorizador' => [
            ':empresa' => 'Nombre de la empresa',
            ':fecha_actualizacion' => 'Fecha en que se guardó este texto',
        ],
        'disclaimer_rit_publicacion' => [
            ':empresa' => 'Nombre de la empresa',
            ':trabajador' => 'Nombre del trabajador',
        ],
        'disclaimer_rit_socializacion' => [
            ':empresa' => 'Nombre de la empresa',
            ':trabajador' => 'Nombre del trabajador',
        ],
        'disclaimer_culminacion_socializacion' => [
            ':empresa' => 'Nombre de la empresa',
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
        ':fecha_actualizacion' => '16 de octubre de 2026',
        ':url_politica' => 'https://admin.ceslegal.co/politica-tratamiento-datos',
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
