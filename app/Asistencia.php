<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Asistencia extends Model
{
    protected $table = 'asistencias';
    
    protected $fillable = [
        'usuario_id', 
        'tipo', 
        'fecha_hora', 
        'ip_address', 
        'user_agent', 
        'dispositivo_info', 
        'observaciones'
    ];

    protected $dates = [
        'fecha_hora',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];

    // Relación con el usuario
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Scope para filtrar por fecha
    public function scopeFechaBetween($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_hora', [$fechaInicio, $fechaFin]);
    }

    // Scope para filtrar por usuario
    public function scopePorUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    // Scope para filtrar por tipo
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    // Obtener el último registro del día para un usuario
    public static function ultimoRegistroDelDia($usuarioId, $fecha = null)
    {
        $fecha = $fecha ?: Carbon::today();
        
        return static::where('usuario_id', $usuarioId)
            ->whereDate('fecha_hora', $fecha)
            ->orderBy('fecha_hora', 'desc')
            ->first();
    }

    // Verificar si el usuario ya marcó ingreso hoy
    public static function yaMarcoIngresoHoy($usuarioId, $fecha = null)
    {
        $fecha = $fecha ?: Carbon::today();
        
        return static::where('usuario_id', $usuarioId)
            ->whereDate('fecha_hora', $fecha)
            ->where('tipo', 'ingreso')
            ->exists();
    }

    // Obtener horas trabajadas en un día
    public static function horasTrabajadasDelDia($usuarioId, $fecha = null)
    {
        $fecha = $fecha ?: Carbon::today();
        
        $registros = static::where('usuario_id', $usuarioId)
            ->whereDate('fecha_hora', $fecha)
            ->orderBy('fecha_hora', 'asc')
            ->get();

        if ($registros->count() < 2) {
            return 0;
        }

        $totalMinutos = 0;
        $ingresos = $registros->where('tipo', 'ingreso');
        $salidas = $registros->where('tipo', 'salida');

        foreach ($ingresos as $index => $ingreso) {
            $salida = $salidas->skip($index)->first();
            if ($salida) {
                $totalMinutos += $ingreso->fecha_hora->diffInMinutes($salida->fecha_hora);
            }
        }

        return round($totalMinutos / 60, 2);
    }

    // Formatear la fecha y hora
    public function getFechaHoraFormateadaAttribute()
    {
        return $this->fecha_hora->format('d/m/Y H:i:s');
    }

    // Obtener solo la fecha
    public function getFechaAttribute()
    {
        return $this->fecha_hora->format('d/m/Y');
    }

    // Obtener solo la hora
    public function getHoraAttribute()
    {
        return $this->fecha_hora->format('H:i:s');
    }
}
