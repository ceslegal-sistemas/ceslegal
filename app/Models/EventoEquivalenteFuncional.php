<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un evento de trazabilidad del "equivalente funcional" (una aceptación o
 * autorización con su evidencia). NO es una tabla: es el resultado de unir, al
 * leer, las evidencias que guarda cada proceso - ver
 * App\Services\EquivalenteFuncionalService::consulta(). Solo lectura.
 *
 * `uid` ("tipo-id") es la llave: el mismo id puede repetirse entre tipos.
 */
class EventoEquivalenteFuncional extends Model
{
    /** Alias de la subconsulta (fromSub); debe coincidir con el usado en el servicio. */
    protected $table = 'eventos_equivalente_funcional';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = [
        'fecha' => 'datetime',
        'tiene_selfie' => 'boolean',
    ];
}
