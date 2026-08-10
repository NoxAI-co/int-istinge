<?php

namespace App\Http\Controllers;

use App\PortalNotificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo master-api: endpoints que consume el Integra Portal.
 * Réplica del controlador homónimo de integra2.0 adaptada a Laravel 7.
 * Autenticación en App\Http\Middleware\VerifyPortalToken.
 */
class MasterApiController extends Controller
{
    /** Salud de la instancia (para el monitoreo del portal, Fase 2). */
    public function health()
    {
        $db = true;
        try {
            DB::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $db = false;
        }

        return response()->json([
            'ok'      => $db,
            'db'      => $db,
            'app'     => config('app.name'),
            'version' => 'int-istinge',
            'hora'    => now()->toIso8601String(),
        ], $db ? 200 : 503);
    }

    /** Recibe (o actualiza) una notificación del portal para el dashboard. */
    public function notificaciones(Request $request)
    {
        $this->asegurarTabla();

        $data = $request->validate([
            'portal_id'         => ['required', 'integer'],
            'portal_empresa_id' => ['nullable', 'integer'],
            'titulo'            => ['required', 'string', 'max:200'],
            'cuerpo'            => ['required', 'string', 'max:10000'],
            'tipo'              => ['required', 'string', 'max:20'],
            'vigente_desde'     => ['nullable', 'date'],
            'vigente_hasta'     => ['nullable', 'date'],
        ]);

        PortalNotificacion::updateOrCreate(
            ['portal_id' => $data['portal_id']],
            $data
        );

        return response()->json(['ok' => true]);
    }

    /** El portal informa el resultado de la revisión de un comprobante. */
    public function comprobanteEstado(Request $request)
    {
        if (! Schema::hasTable('portal_comprobantes')) {
            return response()->json(['ok' => true, 'nota' => 'sin tabla local, nada que actualizar']);
        }

        $data = $request->validate([
            'portal_comprobante_id' => ['required', 'integer'],
            'estado'                => ['required', 'in:aprobado,rechazado,pendiente'],
            'motivo_rechazo'        => ['nullable', 'string', 'max:1000'],
        ]);

        \App\PortalComprobante::where('portal_comprobante_id', $data['portal_comprobante_id'])
            ->update([
                'estado'         => $data['estado'],
                'motivo_rechazo' => isset($data['motivo_rechazo']) ? $data['motivo_rechazo'] : null,
            ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Suspende la suscripción de la empresa. Dos capas:
     *  - suscripciones.fec_corte en el pasado (+ ilimitado=0) activa el modo
     *    lectura real del legado (User::modo_lectura): bloquea escrituras.
     *  - portal_suspendida=1 + portal_suspension_mensaje hacen que el layout
     *    muestre un modal fijo (no descartable, solo cerrar sesión) con el
     *    mensaje configurado desde el Integra Portal.
     */
    public function suspender(Request $request)
    {
        $suscripcion = $this->suscripcionDe($request);
        if (! $suscripcion) {
            return response()->json(['message' => 'No se encontró la suscripción de la empresa.'], 404);
        }

        $this->asegurarColumnasSuspension();
        $suscripcion->update(['fec_corte' => now()->subDay()->toDateString()]);
        // 'ilimitado' anula el corte: apagarlo para que la suspensión aplique.
        DB::table('suscripciones')->where('id', $suscripcion->id)->update([
            'ilimitado'                  => 0,
            'portal_suspendida'          => 1,
            'portal_suspension_mensaje'  => $request->input('mensaje') ?: null,
        ]);

        return response()->json(['ok' => true, 'estado' => 'suspendida']);
    }

    /** Reactiva: extiende fec_corte (payload 'hasta' o día 10 del mes siguiente). */
    public function activar(Request $request)
    {
        $suscripcion = $this->suscripcionDe($request);
        if (! $suscripcion) {
            return response()->json(['message' => 'No se encontró la suscripción de la empresa.'], 404);
        }

        $this->asegurarColumnasSuspension();
        $hasta = $request->input('hasta')
            ?: now()->addMonthNoOverflow()->day(10)->toDateString();
        $suscripcion->update(['fec_corte' => $hasta]);
        DB::table('suscripciones')->where('id', $suscripcion->id)->update([
            'portal_suspendida'         => 0,
            'portal_suspension_mensaje' => null,
        ]);

        return response()->json(['ok' => true, 'estado' => 'activa', 'hasta' => $hasta]);
    }

    /** Auto-provisión de las columnas del modal (BDs sin migraciones). */
    private function asegurarColumnasSuspension(): void
    {
        if (! Schema::hasColumn('suscripciones', 'portal_suspendida')) {
            Schema::table('suscripciones', function ($table) {
                $table->tinyInteger('portal_suspendida')->default(0);
            });
        }

        if (! Schema::hasColumn('suscripciones', 'portal_suspension_mensaje')) {
            Schema::table('suscripciones', function ($table) {
                $table->text('portal_suspension_mensaje')->nullable();
            });
        }
    }

    /** Resuelve la suscripción objetivo: por NIT del payload o primera empresa. */
    private function suscripcionDe(Request $request)
    {
        $empresaId = null;
        if ($request->filled('nit')) {
            $empresaId = DB::table('empresas')->where('nit', $request->input('nit'))->value('id');
        }
        if (! $empresaId) {
            $empresaId = DB::table('empresas')->value('id');
        }

        return $empresaId ? \App\Suscripcion::where('id_empresa', $empresaId)->first() : null;
    }

    /**
     * Auto-provisión: en el legado las BDs de clientes no reciben migraciones,
     * así que la tabla se crea en el primer uso. Idempotente.
     */
    private function asegurarTabla(): void
    {
        if (Schema::hasTable('portal_notificaciones')) {
            return;
        }

        Schema::create('portal_notificaciones', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('portal_id')->unique();
            $table->unsignedBigInteger('portal_empresa_id')->nullable();
            $table->string('titulo', 200);
            $table->text('cuerpo');
            $table->string('tipo', 20)->default('info');
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->timestamp('vista_at')->nullable();
            $table->timestamps();
        });
    }
}
