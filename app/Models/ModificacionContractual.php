<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBufeteOrEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModificacionContractual extends Model
{
    use SoftDeletes, ScopedToBufeteOrEmpresa;

    protected $table = 'modificaciones_contractuales';

    // Estas listas deben reflejar EXACTAMENTE los enum(...) de la migración
    // 2026_08_19_000002_create_modificaciones_contractuales_table.php. Si se
    // agrega un valor aquí, hay que ampliar también el ENUM de la BD con una
    // migración nueva (ALTER TABLE ... MODIFY COLUMN); de lo contrario MySQL
    // trunca/rechaza el insert en silencio (ver incidentes reales de este
    // proyecto: commits 035c509 y 80b9106).
    public const TIPOS = [
        'salario'       => 'Salario',
        'cargo'         => 'Cargo',
        'jornada'       => 'Jornada / Modalidad',
        'tipo_contrato' => 'Tipo de Contrato',
        'plazo'         => 'Plazo (Prórroga)',
    ];

    public const ESTADOS = [
        'borrador'        => 'Borrador',
        'otrosi_generado' => 'Otrosí Generado',
    ];

    protected $fillable = [
        'solicitud_contrato_id',
        'empresa_id',
        'abogado_id',
        'tipo_modificacion',
        'valor_anterior',
        'valor_nuevo',
        'justificacion',
        'fecha_efectiva',
        'texto_otrosi_redactado',
        'ruta_otrosi',
        'fecha_generacion_otrosi',
        'estado',
    ];

    protected $casts = [
        'fecha_efectiva'          => 'date',
        'fecha_generacion_otrosi' => 'datetime',
    ];

    public function solicitudContrato(): BelongsTo
    {
        return $this->belongsTo(SolicitudContrato::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function abogado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abogado_id');
    }

    // ScopedToBufeteOrEmpresa filtra por empresa_id de ESTE modelo, asumiendo
    // que siempre coincide con el empresa_id de su SolicitudContrato. Si eso
    // se desincroniza, un usuario "bufete" vería un historial de modificaciones
    // filtrado/incorrecto sin ningún error visible. Por eso se deriva siempre
    // del contrato real al crear, sin confiar en el valor asignado en masa.
    protected static function booted(): void
    {
        static::creating(function (self $modificacion) {
            if ($modificacion->solicitud_contrato_id) {
                $modificacion->empresa_id = SolicitudContrato::withoutGlobalScopes()
                    ->find($modificacion->solicitud_contrato_id)
                    ?->empresa_id ?? $modificacion->empresa_id;
            }
        });
    }
}
