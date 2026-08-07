{{-- Avisos del Integra Portal vigentes hoy (banner del dashboard).
     Guard de tabla: si el portal nunca ha enviado nada, no existe y no se
     muestra nada. Ocultable por sesión del navegador (sessionStorage). --}}
@php
    $portalNotis = collect();
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('portal_notificaciones')) {
            $portalNotis = \App\PortalNotificacion::vigentes()->orderByDesc('id')->get();
        }
    } catch (\Throwable $e) {
        // Nunca romper el dashboard por un aviso.
    }
@endphp

@if ($portalNotis->isNotEmpty())
    @foreach ($portalNotis as $noti)
        @php
            $estilo = [
                'pago'    => ['borde' => '#7c3aed', 'fondo' => 'rgba(124,58,237,.06)', 'icono' => 'fa-credit-card'],
                'urgente' => ['borde' => '#e11d48', 'fondo' => 'rgba(225,29,72,.06)',  'icono' => 'fa-exclamation-triangle'],
            ][$noti->tipo] ?? ['borde' => '#2563eb', 'fondo' => 'rgba(37,99,235,.06)', 'icono' => 'fa-bell'];

            // Markdown mínimo: **negrilla**. El resto se escapa.
            $cuerpoHtml = preg_replace('/\*\*([^*]+)\*\*/', '<b>$1</b>', e($noti->cuerpo));
            $cuerpoHtml = nl2br($cuerpoHtml);
        @endphp
        <div class="portal-noti mb-3" data-portal-noti="{{ $noti->id }}"
             style="display:none; border:1px solid {{ $estilo['borde'] }}33; border-left:4px solid {{ $estilo['borde'] }}; background:{{ $estilo['fondo'] }}; border-radius:10px; padding:14px 40px 14px 16px; position:relative;">
            <button type="button" onclick="portalNotiOcultar({{ $noti->id }})"
                    style="position:absolute; top:8px; right:10px; border:0; background:transparent; font-size:16px; color:#94a3b8; cursor:pointer;"
                    aria-label="Ocultar aviso">&times;</button>
            <div style="display:flex; gap:12px; align-items:flex-start;">
                <i class="fas {{ $estilo['icono'] }}" style="color: {{ $estilo['borde'] }}; margin-top:3px;"></i>
                <div>
                    <div style="font-weight:700; margin-bottom:4px;">{{ $noti->titulo }}</div>
                    <div style="font-size:.9rem; line-height:1.5;">{!! $cuerpoHtml !!}</div>
                </div>
            </div>
        </div>
    @endforeach

    <script>
        (function () {
            var ocultas = [];
            try { ocultas = JSON.parse(sessionStorage.getItem('portal_notis_ocultas') || '[]'); } catch (e) {}
            document.querySelectorAll('.portal-noti').forEach(function (el) {
                if (ocultas.indexOf(parseInt(el.dataset.portalNoti, 10)) === -1) el.style.display = 'block';
            });
            window.portalNotiOcultar = function (id) {
                try {
                    var lista = JSON.parse(sessionStorage.getItem('portal_notis_ocultas') || '[]');
                    if (lista.indexOf(id) === -1) lista.push(id);
                    sessionStorage.setItem('portal_notis_ocultas', JSON.stringify(lista));
                } catch (e) {}
                var el = document.querySelector('[data-portal-noti="' + id + '"]');
                if (el) el.style.display = 'none';
            };
        })();
    </script>
@endif
