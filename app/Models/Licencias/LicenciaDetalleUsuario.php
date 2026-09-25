<?php

namespace App\Models\Licencias;

use App\Models\TipoUsuario\TipoUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenciaDetalleUsuario extends Model
{
    protected $table = 'licencia_detalle_usuarios';

    protected $fillable = [
        'licencia_id',
        'tipo_usuario_id',
        'cantidad',
        'precio_unitario_aplicado',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario_aplicado' => 'decimal:2',
    ];

    public function licencia(): BelongsTo
    {
        return $this->belongsTo(Licencias::class, 'licencia_id');
    }

    public function tipoUsuario(): BelongsTo
    {
        return $this->belongsTo(TipoUsuario::class, 'tipo_usuario_id');
    }

    public function getSubtotalAttribute(): float
    {
        return $this->cantidad * $this->precio_unitario_aplicado;
    }
}