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
            <p style="margin:14px 0 0; font-size:12px; color:#9ca3af;">El acceso quedará restablecido automáticamente cuando el servicio sea reactivado.</p>
            <form action="{{ route('logout') }}" method="POST" style="margin:22px 0 0;">
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
