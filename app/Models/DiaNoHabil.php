<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiaNoHabil extends Model
{
    protected $table = 'dias_no_habiles';

    protected $fillable = [
        'fecha',
        'descripcion',
        'tipo',
        'recurrente',
    ];

    protected $casts = [
        'fecha' => 'date',
        'recurrente' => 'boolean',
    ];
}
