{{-- ==========================================================================
    Modal FIJO de servicio suspendido, controlado desde el Integra Portal
    (master-api/suspender escribe suscripciones.portal_suspendida y el
    mensaje). No se puede cerrar ni descartar: la única acción es cerrar
    sesión. El modo lectura real lo aplica el legado por fec_corte, así que
    este modal es la cara visible de un bloqueo que ya existe en el servidor.

    Fase siguiente (planeada): adjuntar el comprobante de pago aquí mismo,
    que viaje al portal y un supervisor reactive al validarlo.
========================================================================== --}}
@php
    $portalSuspMensaje = null;
    $portalSuspActiva = false;
    try {
        if (Auth::check() && Auth::user()->empresa) {
            $suscripcionPortal = \Illuminate\Support\Facades\DB::table('suscripciones')
                ->where('id_empresa', Auth::user()->empresa)
                ->first();
            // isset() sobre stdClass: si la BD aún no tiene las columnas
            // (auto-provisión pendiente), simplemente no hay modal.
            if ($suscripcionPortal && isset($suscripcionPortal->portal_suspendida) && (int) $suscripcionPortal->portal_suspendida === 1) {
                $portalSuspActiva = true;
                $portalSuspMensaje = $suscripcionPortal->portal_suspension_mensaje ?? null;
            }
        }
    } catch (\Throwable $e) {
        // Nunca tumbar el layout por el modal.
    }
@endphp

@if ($portalSuspActiva)
    <div id="portal-suspension-overlay"
        style="position:fixed; inset:0; z-index:2147483647; display:flex; align-items:center; justify-content:center; background:rgba(2,6,23,.82); backdrop-filter:blur(4px); padding:16px;">
        <div style="max-width:520px; width:100%; background:#fff; border-radius:16px; border:1px solid rgba(245,158,11,.35); box-shadow:0 24px 60px rgba(0,0,0,.45); padding:36px 32px; text-align:center; font-family:inherit;">
            <div style="width:56px; height:56px; margin:0 auto 16px; border-radius:50%; background:rgba(245,158,11,.15); display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-pause-circle" style="font-size:28px; color:#d97706;"></i>
            </div>
            <h3 style="margin:0 0 12px; font-size:20px; font-weight:700; color:#111827;">Servicio suspendido</h3>
            <div style="font-size:14px; line-height:1.6; color:#4b5563; white-space:pre-line;">{{ trim((string) $portalSuspMensaje) !== '' ? $portalSuspMensaje : 'El servicio de tu empresa se encuentra suspendido temporalmente. Comunícate con tu proveedor del sistema para regularizar la situación y reactivar el acceso.' }}</div>
            {{-- ── Adjuntar el comprobante del pago (mismo canal de Mi suscripción;
                 al aprobarlo, el portal reactiva el servicio automáticamente) ── --}}
            @if (session('success'))
                <div style="margin:16px 0 0; padding:12px 14px; border-radius:10px; border:1px solid rgba(16,185,129,.35); background:rgba(16,185,129,.1); color:#047857; font-size:13px; font-weight:600;">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                </div>
            @else
                @if (session('error'))
                    <div style="margin:16px 0 0; padding:10px 14px; border-radius:10px; border:1px solid rgba(244,63,94,.35); background:rgba(244,63,94,.08); color:#be123c; font-size:13px;">
                        {{ session('error') }}
                    </div>
                @endif
                <form action="{{ route('mi-suscripcion.store') }}" method="POST" enctype="multipart/form-data"
                    style="margin:16px 0 0; padding:14px; border-radius:10px; border:1px solid #e5e7eb; background:#f9fafb; text-align:left;">
                    {{ csrf_field() }}
                    <input type="hidden" name="periodo" value="{{ date('Y-m') }}">
                    <input type="hidden" name="observaciones" value="Enviado desde el aviso de suspensión">
                    <p style="margin:0 0 8px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#6b7280;">
                        ¿Ya pagaste? Adjunta el comprobante ({{ date('Y-m') }})
                    </p>
                    <input type="file" name="archivo" accept=".jpg,.jpeg,.png,.webp,.pdf" required
                        style="display:block; width:100%; font-size:12px; color:#6b7280; margin-bottom:10px;">
                    @if ($errors->has('archivo'))
                        <p style="margin:0 0 8px; font-size:12px; color:#be123c;">{{ $errors->first('archivo') }}</p>
                    @endif
                    <button type="submit"
                        style="display:block; width:100%; padding:9px 0; border-radius:8px; border:0; background:#059669; color:#fff; font-size:13px; font-weight:700; cursor:pointer;">
                        <i class="fas fa-paper-plane"></i> Enviar comprobante a Integra
                    </button>
                    <p style="margin:8px 0 0; font-size:11px; line-height:1.4; color:#9ca3af;">
                        Solo se conserva un comprobante por mes: si adjuntaste el archivo equivocado, envía el correcto y reemplazará al anterior.
                    </p>
                </form>
            @endif

            <p style="margin:14px 0 0; font-size:12px; color:#9ca3af;">El acceso quedará restablecido automáticamente cuando el pago sea confirmado.</p>
            <form action="{{ route('logout') }}" method="POST" style="margin:18px 0 0;">
                {{ csrf_field() }}
                <button type="submit"
                    style="display:inline-flex; align-items:center; gap:8px; padding:10px 22px; border-radius:10px; border:1px solid #d1d5db; background:#f9fafb; color:#111827; font-size:14px; font-weight:600; cursor:pointer;">
                    <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                </button>
            </form>
        </div>
    </div>
    <script>
        // Sin tecla de escape ni clic fuera: el overlay no tiene handlers de
        // cierre. Bloquear también el scroll del fondo.
        document.addEventListener('DOMContentLoaded', function () {
            document.body.style.overflow = 'hidden';
        });
    </script>
@endif
