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

    public const TIPOS = [
        'salario'       => 'Salario',
        'cargo'         => 'Cargo',
        'jornada'       => 'Jornada / Modalidad',
        'tipo_contrato' => 'Tipo de Contrato',
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
}
