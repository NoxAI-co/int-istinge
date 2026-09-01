{{--
    CONTRATO ÚNICO CONVERGENTE — SERVICIOS FIJOS
    Modelo obligatorio de la Resolución CRC 7811 de 2025.

    El formato NO es libre: la CRC define el orden de las secciones, los textos
    y hasta el código de color. Por eso esta plantilla reproduce el modelo tal
    cual y no se "mejora" por gusto:

      · #595959  barra de sección normal, texto blanco
      · #C00000  barra ROJA — solo las secciones que comprometen dinero:
                 «Sobre tus servicios fijos», «Cláusula de permanencia mínima»,
                 «Cambio de domicilio» y «Entrega y devolución de equipos»
      · #4F81BD  azul: aclaraciones y espacios que diligencia el usuario
      · #A6A6A6  gris claro: texto de ejemplo / placeholder

    Las variables las arma AsignacionesController::datosVistaContrato(); el PDF
    lo construye construirPdfContrato(), que además fija el papel en CARTA y
    estampa el pie «Página N de M».

    Ojo con la versión de PHP: producción corre 7.4, así que aquí NO se usan
    `?->` ni str_starts_with().
--}}
@extends('layouts.pdf')

@section('content')
<style type="text/css">
    /* El medio por defecto de dompdf es 'screen': las reglas @media print se
       descartarían, por eso @page va suelto. */
    /* Márgenes a 0 y cada hoja dibujada a tamaño completo: las columnas van
       posicionadas en absoluto. Ver la nota de .hoja para el porqué. */
    @page { margin: 0; }

    body {
        margin: 0;
        padding: 0;
        font-family: "Calibri", "Carlito", "DejaVu Sans", sans-serif;
        font-size: 7.4pt;
        line-height: 1.32;
        color: #000;
    }

    p { margin: 0 0 5.5pt; text-align: justify; }

    /* ─── Rejilla de dos columnas ───────────────────────────────────────── */
    /*
     * Las columnas NO se hacen con una tabla de dos celdas, aunque sea lo
     * obvio: dompdf no sabe partir una fila entre páginas, y aquí cada fila
     * es una página entera. Al no caber, empujaba la fila a la hoja siguiente
     * y dejaba hojas en blanco — el modelo de 5 páginas salía en 16.
     *
     * Con posición absoluta cada hoja se dibuja a tamaño de papel completo
     * (612x792pt) y las dos columnas se clavan en sus coordenadas. dompdf las
     * saca del flujo, así que no pueden provocar saltos de página: los saltos
     * los decide .hoja y solo ella.
     *
     * A cambio hay que vigilar el alto: lo que no quepa en 736pt se sale de la
     * hoja sin avisar. Por eso el reparto de secciones por página está fijado
     * a mano y hay que revisar el PDF al tocar textos.
     *
     * El alto es 770pt y NO los 792pt de la hoja carta: con el alto exacto de
     * la página, dompdf 0.8 desborda por redondeo y parte la hoja en dos. En
     * las hojas con `page-break-after: always` el corte forzado lo tapaba,
     * pero la última (.fin) salía partida: el anexo dejaba su columna derecha
     * en una página aparte. Como las columnas van en absoluto, este alto solo
     * sirve para ocupar la hoja y sobra para las dos.
     */
    .hoja { position: relative; width: 612pt; height: 770pt; page-break-after: always; }
    .hoja.fin { page-break-after: auto; }
    .izq, .der { position: absolute; top: 26pt; width: 263pt; }
    .izq { left: 28pt; }
    .der { left: 321pt; }

    /* ─── Barras de sección ─────────────────────────────────────────────── */
    /* El modelo de la CRC las trae en gris, pero se usa el color de la empresa
       (Configuración → color del sistema): el contrato es un documento de marca
       y el gris plano lo dejaba anónimo. El ROJO se conserva tal cual porque en
       el modelo NO es decorativo: marca las cuatro secciones que comprometen
       dinero, y cambiarlo perdería esa señal. */
    .bar {
        background: {{ $color }}; color: #fff; font-weight: bold;
        padding: 3pt 6pt; margin: 7pt 0 3pt; font-size: 8.2pt;
        letter-spacing: 0.2pt;
    }
    .bar.red  { background: #C00000; }
    .bar.first { margin-top: 0; }

    /* ─── Utilidades de color del modelo ────────────────────────────────── */
    .azul { color: #4F81BD; }
    .ph   { color: #A6A6A6; }
    .nota { font-size: 6.6pt; }
    b, strong { font-weight: bold; }

    /* ─── Caja de apertura (texto blanco sobre el color de la empresa) ──── */
    .apertura {
        background: {{ $color }}; color: #fff; padding: 7pt 8pt;
        text-align: justify; margin: 0;
    }

    /* ─── Casillas de verificación ──────────────────────────────────────── */
    .chk {
        display: inline-block; width: 9pt; height: 9pt;
        border: 1px solid #000; margin-left: 4pt;
    }
    .chk-lleno { background: {{ $color }}; }
    /* Dentro de la banda de apertura el fondo YA es el color de la empresa, así
       que rellenar la casilla con ese mismo color la deja idéntica a una sin
       marcar: la aceptación de la renovación automática no se distinguía. Ahí
       la casilla se invierte y va en blanco. */
    .apertura .chk { border-color: #fff; }
    .apertura .chk-lleno { background: #fff; }

    /* ─── Tablas del modelo ─────────────────────────────────────────────── */
    table.m { width: 100%; border-collapse: collapse; margin-bottom: 5pt; }
    table.m th, table.m td { border: 1px solid #B4B4B4; padding: 3pt 4.5pt; vertical-align: middle; }
    table.m th { background: {{ $color }}; color: #fff; font-weight: bold; text-align: center; }
    table.m td.et { background: {{ $color }}; color: #fff; }
    table.m td.gris { background: #E7E7E7; }

    /* Ficha del usuario: recuadro con líneas de relleno */
    .ficha { border: 1px solid #B4B4B4; border-top: none; padding: 5pt 6pt; }
    .ficha div { margin-bottom: 2.5pt; }
    .linea { border-bottom: 1px solid #A6A6A6; }

    /* Huecos de maquetación.
       En el PDF de referencia estos espacios traían escrito «ESPACIO EN BLANCO»,
       pero eso era el marcador de posición de ESE operador, no parte del modelo
       de la CRC: son simplemente el aire que hace que las dos columnas cierren
       parejas. Van vacíos, sin recuadro ni texto. */
    .hueco { }
    .hueco.chico { height: 150pt; }
    .hueco.grande { height: 118pt; }

    /* El pie NO va aquí: lo estampa construirPdfContrato() con page_text(),
       porque el total de páginas depende del largo de «Otras condiciones». */

    /* Sección que SÍ fluye entre páginas: el texto libre del operador puede
       ocupar varias hojas y con posición absoluta se recortaba en silencio. */
    .fluida { padding: 30pt 28pt 34pt 28pt; page-break-after: always; }

    /* Cabecera del operador */
    .ident { font-size: 5.6pt; text-align: center; line-height: 1.25; }
    .titulo { font-size: 9pt; font-weight: bold; line-height: 1.18; color: {{ $color }}; }

    /* Anexos de evidencia (documento e imágenes A..H de la asignación) */
    .evidencia { page-break-before: always; padding: 30pt 28pt; text-align: center; }
    .evidencia img { max-width: 520pt; }
</style>

@php
    /* ── Datos del operador ────────────────────────────────────────────── */
    $nit = trim(($company->nit ?? '').(($company->dv ?? '') !== '' && ($company->dv ?? null) !== null ? '-'.$company->dv : ''));
    $registroTic = $company->registro_tic ?? null;          // lo agrega la migración CRC
    $incremento  = $company->incremento_tarifario ?? null;   // idem

    /* ── Casillas de servicios ─────────────────────────────────────────── */
    $marca = function ($si) { return '<span class="chk'.($si ? ' chk-lleno' : '').'"></span>'; };

    /* ── Permanencia: la tabla mes a mes se calcula, no se guarda ──────── */
    $meses    = (int) ($permanenciaMeses ?: 12);
    $diferido = (float) ($contract->valor_diferido ?? 0);
    $cargoCon = (float) ($contract->cargo_conexion ?? 0);
    $cuotas   = [];
    if ($diferido > 0 && $meses > 0) {
        // El valor a pagar decrece linealmente: lo que falta por amortizar.
        for ($i = 1; $i <= $meses; $i++) {
            $cuotas[$i] = $diferido * (($meses - $i + 1) / $meses);
        }
    }

    $pesos = function ($v) use ($moneda) { return $moneda.' '.\App\Funcion::Parsear((float) $v); };

    /* ── QR: lleva al sitio del operador; si no hay, no se pinta ─────────
       QrCode::format('png') necesita la extensión imagick. Donde no está, el
       binario que devuelve no es un PNG y la etiqueta saldría rota: mejor no
       pintar nada. */
    $qr = null;
    if (! empty($webEmpresa) && extension_loaded('imagick')) {
        try {
            $qr = 'data:image/png;base64,'.base64_encode(
                QrCode::format('png')->size(200)->generate($webEmpresa)
            );
        } catch (\Throwable $e) { $qr = null; }
    }

    /* Varias bases guardan la velocidad en kbps con sufijo k ("100128k") y
       otras ya traen el "M" en el nombre del plan ("300M"), así que imprimir el
       valor crudo daba «Plan 100128k Mbps». Se normaliza acá. */
    $vel = function ($v) {
        $v = trim((string) $v);
        $v = preg_replace('/\s*mbps\s*$/i', '', $v);          // "20 Mbps" -> "20"
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*k$/i', $v, $m)) {
            return round(((float) str_replace(',', '.', $m[1])) / 1024).' Mbps';
        }
        $v = preg_replace('/^(\d+(?:[.,]\d+)?)\s*M$/i', '$1', $v);  // "300M" -> "300"
        return $v === '' ? '' : $v.' Mbps';
    };

    $fmt = function ($f) { return $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : null; };
    /* Plazo legal de instalación: 15 días hábiles desde la suscripción. */
    $limiteInstalacion = isset($contract->created_at)
        ? \Carbon\Carbon::parse($contract->created_at)->addWeekdays(15)->format('d/m/Y')
        : null;

    /* La firma NO se guarda como data-URI: es base64 crudo y con un carácter de
       más al principio. Por eso el substr, igual que hacía la plantilla
       anterior. El umbral de 100 descarta los trazos vacíos que deja el canvas
       cuando el cliente no llegó a firmar. */
    $firmaSrc = null;
    $f = $digital->firma ?? null;
    if ($f && strlen(trim($f)) > 100) {
        $firmaSrc = substr($f, 0, 5) === 'data:' ? $f : 'data:image/png;base64,'.substr($f, 1);
    }
@endphp

{{-- ══════════════════════════ PÁGINA 1 ══════════════════════════════════ --}}
<div class="hoja">
<div class="izq">

    {{-- Cabecera: logo · QR · título --}}
    <table style="width:100%; border-collapse:collapse; margin-bottom:7pt;">
        <tr>
            <td style="width:44%; vertical-align:top; padding-right:3pt;">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" style="max-width:100pt; max-height:34pt;">
                @else
                    <div style="font-weight:bold; font-size:7.5pt; line-height:1.15;">{{ $nombreEmpresa }}</div>
                @endif
                <div class="ident">
                    @if($nit)NIT: {{ $nit }}@endif
                    @if($registroTic)<br>REGISTRO TIC No. {{ $registroTic }}@endif
                    @if($company->direccion ?? null)<br>{{ $company->direccion }}@endif
                    @if($company->telefono ?? null)<br>{{ $company->telefono }}@endif
                    @if($emailEmpresa)<br>{{ $emailEmpresa }}@endif
                </div>
            </td>
            <td style="width:18%; vertical-align:top; text-align:center;">
                @if($qr)<img src="{{ $qr }}" style="width:44pt; height:44pt;">@endif
            </td>
            <td style="width:38%; vertical-align:middle; padding-left:3pt;">
                <div class="titulo">CONTRATO ÚNICO CONVERGENTE SERVICIOS FIJO</div>
            </td>
        </tr>
    </table>

    <div class="apertura">
        Este contrato explica las condiciones para la prestación de los servicios
        entre usted y <b>{{ $nombreEmpresa }}</b>, por el que pagará mínimo
        mensualmente el valor señalado en la sección «Valor de los servicios
        contratados». Este contrato tendrá vigencia de {{ $meses }} meses, contados
        a partir de la fecha de suscripción de este contrato. Acepto que mi contrato
        se renueve sucesiva y automáticamente por un plazo igual a la inicial
        {!! $marca((int) ($contract->renovacion_automatica ?? 0) === 1) !!} *
    </div>
    <div class="azul nota">* Espacio diligenciado por el usuario</div>

    <div class="bar">LOS SERVICIOS</div>
    <p>Con este contrato nos comprometemos a prestarle los servicios que usted elija*:</p>

    <p style="margin-bottom:4pt;">
        Internet fijo {!! $marca($tieneInternet) !!}
        &nbsp;&nbsp;&nbsp; Televisión {!! $marca($tieneTV) !!}
        &nbsp;&nbsp;&nbsp; Telefonía fija {!! $marca(false) !!}
    </p>
    <p class="azul">El cobro por la efectiva prestación de los servicios fijos se
        realizará a partir de su instalación.</p>

    <p style="margin-bottom:4pt;">
        Internet móvil {!! $marca(false) !!}
        &nbsp;&nbsp;&nbsp; Telefonía móvil {!! $marca(false) !!}
        &nbsp;&nbsp;&nbsp; SMS {!! $marca(false) !!}
    </p>
    <p class="azul">El cobro por la efectiva prestación de los servicios móviles se
        realizará a partir de la activación del plan.</p>

    <p>Productos o servicios adicionales
        <span class="linea">&nbsp;{!! ($contract->servicios_adicionales ?? null) ?: str_repeat('&nbsp;', 26) !!}&nbsp;</span>.</p>

    <p class="azul">El cobro por la efectiva prestación de los productos o servicios
        adicionales se realizará a partir de {{ $fmt($contract->created_at ?? null) ?: 'dd/mm/aaaa' }}</p>

    <p>Usted se compromete a pagar oportunamente el (los) precio(s) acordado(s).</p>

    <div class="bar" style="margin-bottom:0;">INFORMACIÓN DEL USUARIO</div>
    <div class="ficha">
        <div>Contrato Nº: <span class="linea">&nbsp;{{ $contratoNro }}&nbsp;</span></div>
        <div>Nombre / Razón Social <span class="linea">&nbsp;{{ $contactoNombreCompleto }}&nbsp;</span></div>
        <div>Identificación <span class="linea">&nbsp;{{ $tipoIden }} {{ $contact->nit ?? '' }}&nbsp;</span></div>
        <div>Correo electrónico <span class="linea">&nbsp;{{ $contact->email ?? '' }}&nbsp;</span></div>
        <div>Teléfono de contacto <span class="linea">&nbsp;{{ $contact->celular ?? '' }}&nbsp;</span></div>
        <div>Dirección Servicio fijo
            <span class="linea">&nbsp;{{ $contract->address_street ?? $contact->direccion ?? '' }}&nbsp;</span>
            Estrato <span class="linea">&nbsp;{{ $contact->estrato ?? '' }}&nbsp;</span></div>
        <div>Departamento <span class="linea">&nbsp;{{ $departamento }}&nbsp;</span>
            Municipio <span class="linea">&nbsp;{{ $municipio }}&nbsp;</span></div>
        <div>Línea o número móvil <span class="linea">&nbsp;{{ $contact->celular ?? '' }}&nbsp;</span></div>
        <div>Dirección del suscriptor <span class="linea">&nbsp;{{ $contact->direccion ?? '' }}&nbsp;</span></div>
    </div>

</div>
<div class="der">

    <div class="bar first">CONDICIONES COMERCIALES PLAN SERVICIOS FIJOS</div>
    <table class="m">
        <tr><td>Internet fijo</td></tr>
        {{-- Si el plan quedó huérfano (plan_id borrado) no hay velocidad que
             imprimir: se cae al placeholder del modelo en vez de dejar un
             «Plan  de bajada /  de subida». --}}
        @php $velTexto = trim($vel($planDownload).$vel($planUpload)); @endphp
        <tr><td class="{{ $tieneInternet && $velTexto !== '' ? '' : 'gris' }}">
            @if($tieneInternet && $velTexto !== '')
                Plan {{ $vel($planDownload) }} de bajada / {{ $vel($planUpload) }} de subida
                @if($tecnologia && ! is_numeric($tecnologia)) · {{ $tecnologia }}@endif
            @else
                <span class="ph">Descripción</span>
            @endif
        </td></tr>
        <tr><td>Televisión cerrada</td></tr>
        <tr><td class="{{ $tieneTV ? '' : 'gris' }}">
            @if($tieneTV) Servicio de televisión cerrada @else <span class="ph">Descripción</span> @endif
        </td></tr>
        <tr><td>Telefonía fija</td></tr>
        <tr><td class="gris"><span class="ph">Descripción</span></td></tr>
        <tr><td>Servicios adicionales</td></tr>
        <tr><td class="{{ ($contract->servicios_adicionales ?? null) ? '' : 'gris' }}">
            @if($contract->servicios_adicionales ?? null)
                {{ $contract->servicios_adicionales }}
            @else <span class="ph">Descripción</span> @endif
        </td></tr>
    </table>

    <div class="bar">VALOR DE LOS SERVICIOS CONTRATADOS</div>
    <table class="m">
        <tr>
            <td class="et" style="width:62%; text-align:right;">VALOR MENSUAL PLAN SERVICIOS FIJOS</td>
            <td><b>{{ $moneda }} {{ $totalStr }}</b></td>
        </tr>
        <tr>
            <td class="et" style="text-align:right;">VALOR MENSUAL SERVICIOS EMPAQUETADOS</td>
            <td><b>{{ ($tieneInternet && $tieneTV) ? $moneda.' '.$totalStr : $moneda }}</b></td>
        </tr>
    </table>

    <div class="bar">BENEFICIOS DEL PAQUETE DE SERVICIOS:</div>
    <div style="border:1px solid #7F7F7F; padding:4pt 5pt; margin-bottom:5pt;">
        <p style="margin-bottom:3pt;">Los beneficios del paquete de servicios son:</p>
        @if($contract->beneficios_paquete ?? null)
            <p style="margin:0;">{{ $contract->beneficios_paquete }}</p>
        @else
            <p class="ph" style="text-align:center; margin:0;">Espacio para diligenciar por el
                operador con ocasión del ofrecimiento de un paquete de servicios.</p>
        @endif
    </div>

    <div class="bar">INCREMENTOS TARIFARIOS</div>
    <p>{{ $nombreEmpresa }} podrá incrementar anualmente el valor de su plan hasta un
        máximo de {!! $incremento !== null ? $incremento.' %' : '<span class="linea">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span> %' !!}
        del precio vigente. El cambio solo regirá en el periodo siguiente al aviso del
        incremento, que se enviará por el mismo canal de la factura al menos cinco (5)
        días hábiles antes de que termine el ciclo de facturación en curso.</p>

    <p>Si la nueva tarifa no le conviene, podrá terminar el contrato pagando solo lo
        adeudado hasta la fecha. Además, cualquier aumento fuera de lo pactado o sin su
        autorización le permite dar por terminado el contrato sin penalidades, incluso
        si existe cláusula de permanencia mínima.</p>

    <div class="bar">PRINCIPALES OBLIGACIONES DEL USUARIO</div>
    <p>1) pagar oportunamente los servicios prestados, incluyendo los intereses de mora
        cuando haya incumplimiento; 2) suministrar información verdadera; 3) Hacer uso
        adecuado de los equipos, la SIM y los servicios; 4) no divulgar ni acceder a
        pornografía infantil (consultar anexo); 5) avisar a las autoridades cualquier
        evento de</p>

</div>
</div>

{{-- ══════════════════════════ PÁGINA 2 ══════════════════════════════════ --}}
<div class="hoja">
<div class="izq">

    <p>robo o hurto de elementos de la red, como el cable y avisar al operador en caso
        de robo o pérdida de la SIM o el equipo móvil; 6) usar equipos móviles
        homologados y 7) abstenerse de usar equipos hurtados; 8) No cometer o ser
        partícipe de actividades de fraude; 9) hacer uso adecuado de su derecho a
        presentar PQR; 10) actuar de buena fe; 11) mantener actualizada la información
        de contacto.</p>

    <p>El operador podrá terminar el contrato ante incumplimiento de estas obligaciones.</p>

    <div class="bar">CONDICIONES DE ACTIVACIÓN O INSTALACIÓN</div>
    <p><b>Para los servicios fijos</b>:</p>
    <p>El plazo máximo de instalación es de 15 días hábiles, contados a partir de la
        suscripción del presente contrato, es decir, a más tardar día
        <span class="azul">{{ $limiteInstalacion ?: 'dd/mm/aaaa' }}</span>.</p>
    <p>En caso de que no sea posible la activación o instalación de alguno de los
        servicios contratados, usted podrá terminar el contrato sin costo alguno, ni
        penalidades, incluso si existe cláusula de permanencia mínima. Adicionalmente,
        usted tiene derecho a solicitar la devolución del dinero en caso de haber hecho
        un pago anticipado.</p>

    <div class="bar">CALIDAD Y COMPENSACIÓN</div>
    <p>Cuando se presente indisponibilidad del servicio o este se suspenda a pesar de su
        pago oportuno, lo compensaremos en su próxima factura. Debemos cumplir con las
        condiciones de calidad definidas por la CRC. Consúltelas en nuestra página
        {{ $webEmpresa ?: '____________________' }}</p>

    <div class="bar">CESIÓN</div>
    <p>Si quiere ceder este contrato a otra persona, debe presentar una solicitud por
        escrito a través de nuestros Medios de Atención, acompañada de la aceptación por
        escrito de la persona a la que se hará la cesión. Dentro de los 15 días hábiles
        siguientes, analizaremos su solicitud y le daremos una respuesta. Si se acepta
        la cesión queda liberado de cualquier responsabilidad con nosotros.</p>

    <div class="bar">MODIFICACIÓN O TERMINACIÓN</div>
    <div class="hueco chico"></div>

</div>
<div class="der">

    <p style="margin-top:0;">Nosotros no podemos modificar el contrato sin su
        autorización. Esto incluye que no podemos cobrarle servicios que no haya
        aceptado expresamente. Si esto ocurre tiene derecho a terminar el contrato,
        incluso estando vigente la cláusula de permanencia mínima, aplicable únicamente
        para servicios fijos, sin la obligación de pagar suma alguna por este concepto.</p>

    <p>No obstante, usted puede en cualquier momento solicitar la modificación o
        terminación de los servicios contratados. Dicha modificación o terminación se
        hará efectiva en el período de facturación siguiente, para lo cual deberá
        presentar la respectiva solicitud por lo menos con 3 días hábiles de anterioridad
        a la fecha de corte de facturación, informada en su factura.</p>

    <p>Usted puede modificar o cancelar cualquiera de los servicios contratados, sean
        del segmento fijo o del segmento móvil, de manera independiente, caso en el cual,
        durante el periodo de facturación siguiente le enviaremos copia del contrato
        ajustado por el medio que usted elija.</p>

    <div class="bar">SUSPENSIÓN</div>
    <p><b>Servicios fijos:</b> Usted tiene derecho a solicitar la suspensión del servicio
        por un máximo de 2 meses al año. Para esto debe presentar la solicitud antes del
        inicio del ciclo de facturación que desea suspender. Si existe una cláusula de
        permanencia mínima, su vigencia se prorrogará por el tiempo que dure la suspensión.</p>

    <div class="bar">PAGO Y FACTURACIÓN</div>
    <p>Si usted está adquiriendo un paquete de servicios, recibirá una sola factura por
        esos servicios, en la que podrá diferenciar el precio correspondiente a cada
        servicio y al paquete completo. Si el paquete de servicios incluye servicios del
        segmento móvil y del segmento fijo, usted podrá recibir una factura por cada
        segmento.</p>

    <p>La factura le debe llegar como mínimo 5 días hábiles antes de la fecha de pago.
        Si no llega, puede solicitarla a través de nuestros Medios de Atención y debe
        pagarla oportunamente. Usted puede pagar los servicios fijos y móviles de manera
        separada.</p>

    <p>Si no paga a tiempo, previo aviso, suspenderemos su servicio hasta que pague sus
        saldos pendientes. Los servicios fijos y móviles se suspenderán de manera separada
        dependiendo sobre cuál de ellos recae el impago. Contamos con 3 días hábiles luego
        de su pago para reconectarle el servicio.</p>

</div>
</div>

{{-- ══════════════════════════ PÁGINA 3 ══════════════════════════════════ --}}
<div class="hoja">
<div class="izq">

    <p style="margin-top:0;">Si no paga a tiempo, también podemos reportar su deuda a las
        centrales de riesgo. Para esto tenemos que avisarle por lo menos una vez con 20
        días calendario de anticipación, si su deuda es superior al 15% del salario mínimo
        legal mensual vigente (SMLMV). Si su deuda es igual o inferior al 15% del SMLMV,
        tenemos que avisarle al menos en dos ocasiones, en días diferentes, y solo podremos
        reportarlo pasados 20 días a partir de la segunda comunicación. Si paga luego de
        este reporte tenemos la obligación dentro del mes de siguiente de informar su pago
        para que ya no aparezca reportado.</p>

    <p>Si tiene un reclamo sobre su factura, puede presentarlo antes de la fecha de pago y
        en ese caso no debe pagar las sumas reclamadas hasta que resolvamos su solicitud.
        Si ya pagó, tiene 6 meses para presentar la reclamación.</p>

    <div class="bar">LARGA DISTANCIA (TELEFONÍA)</div>
    <p>Nos comprometemos a usar el operador de larga distancia que usted nos indique, para
        lo cual debe marcar el código de larga distancia del operador que elija.</p>

    <div class="bar">COBRO POR RECONEXIÓN DEL SERVICIO</div>
    <p>En caso de suspensión del servicio por mora en el pago, podremos cobrarle un valor
        por reconexión que corresponderá estrictamente a los costos asociados a la
        operación de reconexión. En caso de servicios empaquetados procede máximo un cobro
        de reconexión por cada tipo de conexión (medio de transmisión) empleado en la
        prestación de los servicios.</p>

    <table class="m">
        <tr>
            <td style="width:62%;">Costo reconexión servicios fijos:</td>
            <td><b>{{ $pesos($costoReconexion) }}</b></td>
        </tr>
    </table>

    <div class="bar" style="text-align:center;">CÓMO COMUNICARSE CON NOSOTROS<br>(MEDIOS DE ATENCIÓN)</div>
    <table class="m">
        <tr>
            <td class="et" style="width:9%; text-align:center; font-weight:bold; font-size:11pt;">1</td>
            <td>Nuestros medios de atención son: oficinas físicas
                @if($company->direccion ?? null){{ $company->direccion }}@endif,
                página web {{ $webEmpresa ?: '____________' }},
                líneas telefónicas gratuitas {{ $company->telefono ?? '____________' }}.
                Correo electrónico {{ $emailEmpresa ?: '____________' }}
                Consulte las interacciones que hemos migrado a la digitalización en
                nuestra página web.</td>
        </tr>
        <tr>
            <td class="et" style="text-align:center; font-weight:bold; font-size:11pt;">2</td>
            <td>Presente cualquier queja, petición o recurso a través de estos medios y le
                responderemos en máximo 15 días hábiles.</td>
        </tr>
        <tr>
            <td class="et" style="text-align:center; font-weight:bold; font-size:11pt;">3</td>
            <td>Si no respondemos es porque aceptamos su petición o reclamo. Esto se llama
                silencio administrativo positivo.</td>
        </tr>
        <tr>
            <td colspan="2"><b>Si no está de acuerdo con nuestra respuesta</b></td>
        </tr>
    </table>

</div>
<div class="der">

    <table class="m" style="margin-top:0;">
        <tr>
            <td class="et" style="width:9%; text-align:center; font-weight:bold; font-size:11pt;">4</td>
            <td>Tiene la opción de insistir en su reclamo ante nosotros y pedir que, si no
                llegamos a una solución satisfactoria para usted, enviemos su reclamo
                directamente a la Superintendencia de Industria y Comercio para que resuelva
                de manera definitiva la disputa. Esto se llama recurso de reposición y en
                subsidio de apelación.</td>
        </tr>
    </table>

    <div class="bar red">SOBRE TUS SERVICIOS FIJOS</div>
    <div class="bar red">CLÁUSULA DE PERMANENCIA MÍNIMA</div>

    <p><b>ACEPTO LA CLÁUSULA DE PERMANENCIA MÍNIMA</b>
        {!! $marca((int) ($contract->acepta_permanencia ?? ($clausula > 0 ? 1 : 0)) === 1) !!} *</p>
    <div class="azul nota" style="margin-bottom:5pt;">* Espacio diligenciado por el usuario</div>

    <p>La presente cláusula de permanencia mínima, en caso de ser aceptada por usted, solo
        aplica respecto del plan de servicios fijos, NO APLICA SOBRE EL PLAN DE SERVICIOS
        MÓVILES.</p>

    <p>Se incluye esta cláusula en consideración a que le estamos otorgando un descuento
        respecto del valor del cargo por conexión, o le diferimos el pago del mismo, con
        ocasión de la instalación del servicio por primera vez o por una nueva instalación
        generada por el cambio de domicilio. En cualquier caso, solo estaría vigente una
        cláusula de permanencia mínima. En la factura encontrará el valor a pagar si decide
        terminar el contrato anticipadamente.</p>

    <table class="m">
        <tr>
            <td class="et" style="width:58%;">Valor total del cargo por conexión</td>
            <td>{{ $cargoCon > 0 ? $pesos($cargoCon) : $moneda }}</td>
        </tr>
        <tr>
            <td class="et">Suma que le fue descontada o diferida del valor total del cargo de conexión</td>
            <td>{{ $diferido > 0 ? $pesos($diferido) : $moneda }}</td>
        </tr>
        <tr>
            <td class="et">Fecha de inicio de la permanencia mínima</td>
            <td class="azul">{{ $fechaCreacion ?: 'DD/MM/AAAA' }}</td>
        </tr>
        <tr>
            <td class="et">Fecha de finalización de la permanencia mínima</td>
            <td class="azul">{{ $fechaFinPermanencia ?: 'DD/MM/AAAA' }}</td>
        </tr>
    </table>

    <table class="m">
        <tr><th colspan="6">Valor a pagar si termina el contrato anticipadamente según mes</th></tr>
        @for($fila = 0; $fila < 2; $fila++)
            <tr>
                @for($c = 1; $c <= 6; $c++)
                    @php $m = $fila * 6 + $c; @endphp
                    <td style="text-align:left; font-size:6pt; padding:3pt 2pt;">
                        @if($m <= $meses)
                            Mes {{ $m }}<br>{{ isset($cuotas[$m]) ? $pesos($cuotas[$m]) : $moneda.'...' }}
                        @endif
                    </td>
                @endfor
            </tr>
        @endfor
    </table>

    <p>Encontrará los detalles sobre la permanencia mínima de los servicios fijos en su factura.</p>

    <div class="bar red">CAMBIO DE DOMICILIO</div>
    <p>Usted puede cambiar de domicilio y continuar con el servicio siempre que sea
        técnicamente posible. Si desde el punto de vista técnico no es viable el traslado
        del servicio, usted puede ceder su contrato a un tercero o terminarlo pagando el
        valor de la cláusula de permanencia mínima si está vigente.</p>

</div>
</div>

{{-- ══════════════════════════ PÁGINA 4 ══════════════════════════════════ --}}
<div class="hoja">
<div class="izq">

    <div class="bar red first">ENTREGA Y DEVOLUCIÓN DE EQUIPOS</div>
    <p>{{ $nombreEmpresa }} entrega los equipos en el plan de servicios fijos bajo las
        siguientes condiciones:</p>

    <table class="m">
        <tr>
            <th style="width:30%;">Equipo</th>
            <th style="width:42%;">Condición de entrega</th>
            <th>Precio</th>
        </tr>
        @php $equipos = $equiposContrato ?? []; @endphp
        @forelse($equipos as $eq)
            <tr>
                <td>{{ $eq->equipo ?? '' }}</td>
                <td>{{ $eq->condicion ?? '' }}</td>
                <td>{{ isset($eq->precio) ? $pesos($eq->precio) : '' }}</td>
            </tr>
        @empty
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        @endforelse
    </table>

    <p>Al terminar este contrato usted deberá devolver los equipos entregados. En este
        sentido, deberá atender la cita que se programe para la recolección de los equipos
        en el lugar de instalación. En caso de no atender esta cita, los equipos deberán
        ser entregados en el centro de atención que le indiquemos.</p>

    <p>El hurto, pérdida o daño de equipos deberá reportarse a través de las líneas
        dispuestas en la sección “Como comunicarse con nosotros (Medios de atención)”.</p>

</div>
<div class="der">

    <div style="border:1px solid #B4B4B4; padding:9pt; text-align:center; margin-bottom:4pt;">
        @if($firmaSrc)
            <img src="{{ $firmaSrc }}" style="max-height:46pt; max-width:160pt;">
        @else
            <div style="height:46pt;"></div>
        @endif
        <div style="border-top:1px solid #000; padding-top:3pt; font-weight:bold;">
            Aceptación contrato mediante firma o cualquier otro medio válido
        </div>
        <div style="margin-top:8pt;"><b>Fecha</b>
            <span class="azul">{{ $fmt($digital->fecha_firma ?? $contract->created_at ?? null) ?: 'DD/MM/AAAA' }}</span>
        </div>
    </div>
    <div class="ph nota" style="margin-bottom:8pt;">Consulte el régimen de protección de los
        derechos de los usuarios de comunicaciones en www.crcom.gov.co</div>

    <div class="hueco grande"></div>

</div>
</div>

{{-- ═══════════════ OTRAS CONDICIONES (fluye las hojas que haga falta) ══════ --}}
@if($contratoDigitalTexto)
<div class="fluida">
    <div class="bar first">OTRAS CONDICIONES</div>
    <div style="text-align:justify;">{!! nl2br(e($contratoDigitalTexto)) !!}</div>
</div>
@endif

{{-- ══════════════════════════ PÁGINA 5 · ANEXO ══════════════════════════ --}}
<div class="hoja fin">
<div class="izq">

    <table style="width:100%; border-collapse:collapse; margin-bottom:7pt;">
        <tr>
            <td style="width:55%; vertical-align:middle;">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" style="max-width:95pt; max-height:32pt;">
                @endif
                <div style="font-weight:bold; margin-top:3pt;">Anexo de disposiciones legales</div>
            </td>
            <td style="text-align:center; vertical-align:middle;">
                @if($qr)<img src="{{ $qr }}" style="width:42pt; height:42pt;">@endif
            </td>
        </tr>
    </table>

    <div class="bar">1. AUTORIZACIÓN CENTRALES DE RIESGO</div>
    <p>Autorizo voluntariamente, para que reporte, consulte y divulgue a cualquier operador
        y/o fuente de información legalmente establecido, toda la información referente a mi
        comportamiento como cliente que se relacione con el nacimiento, ejecución,
        modificación, liquidación y/o extinción de las obligaciones que se deriven del
        presente contrato, en cualquier tiempo, y que podrá reflejarse en las bases de datos
        de DATACREDITO, CIFIN, COVINOC o de cualquier otro operador y/o fuente de información
        legalmente establecido. La permanencia de la información estará sujeta a los
        principios, términos y condiciones consagrados en la ley 1266 de 2008 y demás normas
        que lo modifiquen, aclaren o reglamenten. Así mismo, autorizo, expresa e
        irrevocablemente a {{ $nombreEmpresa }}, para que consulte toda la información
        financiera, crediticia, comercial, de servicios y la proveniente de otros países,
        atinente a mis relaciones comerciales que tenga con el Sistema Financiero, comercial
        y de servicios, o de cualquier sector, tanto en Colombia como en el Exterior, en
        cualquier tiempo. PARÁGRAFO: La presente autorización se extiende para que
        {{ $nombreEmpresa }} pueda compartir información con terceros públicos o privados,
        bien sea que éstos ostenten la condición de fuentes de información, operadores de
        información o usuarios, con quienes EL CLIENTE tenga vínculos jurídicos de cualquier
        naturaleza, todo conforme a lo establecido en las normas legales vigentes dentro del
        marco del Sistema de Administración de Riesgos de Lavado de Activos y Financiación al
        Terrorismo SARLAFT de {{ $nombreEmpresa }}
        <span class="ph">acorde con las disposiciones de la Ley 1266 de 2008.</span></p>

    <div class="bar">2. AUTORIZACIÓN PARA EL TRATAMIENTO DE DATOS PERSONALES</div>
    <p>Autorizo de manera voluntaria, previa, explícita, informada e inequívoca a
        {{ $nombreEmpresa }} para tratar mis datos personales de acuerdo con la Política de
        Tratamiento de Datos Personales de la empresa y para los fines relacionados con su
        objeto social y en especial para fines legales, contractuales, comerciales descritos
        en la Política de Tratamiento de Datos Personales de Empresa. La información obtenida
        para el Tratamiento de mis datos</p>

</div>
<div class="der">

    <p style="margin-top:0;">personales la he suministrado de forma voluntaria y es verídica
        acorde con las disposiciones de la Ley 1581 de 2012</p>

    <div class="bar">3. PORNOGRAFÍA INFANTIL</div>
    <p>En cumplimiento de la Ley 679 de 2001 y conforme a lo establecido en los artículos 4 y
        5 del Decreto 1524 de 2012, los proveedores o servidores, administradores y usuarios
        de redes globales de información no podrán alojar en su propio sitio: a). imágenes,
        textos o archivos que impliquen directa o indirectamente actividades sexuales con
        menores de edad, b). material pornográfico, cuando existan indicios que las personas
        fotografiadas o filmadas son menores de edad, c). vínculos sobre sitios telemáticos
        que contengan o distribuyan material pornográfico relativo a menores de edad. Deberá:
        a. Denunciar ante las autoridades competentes cualquier acto criminal contra menores
        de edad que tenga conocimiento. b. Combatir con todos los medios técnicos a su alcance
        la difusión de material pornográfico asociado a menores. c. Abstenerse de usar las
        redes globales de información para la divulgación de material ilegal con menores de
        edad. d. Establecer mecanismos técnicos de bloqueo por medio de los cuales los
        usuarios puedan proteger a sí mismos o a sus hijos de material ilegal, ofensivo o
        indeseable en relación con menores de edad. El incumplimiento de estas prohibiciones
        acarreará las sanciones administrativas y penales contempladas en las normas
        señaladas.</p>

    <div style="margin-top:22pt; text-align:center;">
        @if($firmaSrc)
            <img src="{{ $firmaSrc }}" style="max-height:44pt; max-width:150pt;">
        @else
            <div style="height:44pt;"></div>
        @endif
    </div>
    <div style="border-top:1px solid #000; margin:0 20pt; padding-top:3pt; text-align:center; font-size:9pt;">Firma del Suscriptor</div>
    <table class="m" style="margin-top:2pt;">
        <tr><td>TIPO DE DOCUMENTO: {{ $tipoIden }}</td></tr>
        <tr><td>NÚMERO DOCUMENTO: {{ $contact->nit ?? '' }}</td></tr>
        <tr><td>FECHA: {{ $fmt($digital->fecha_firma ?? $contract->created_at ?? null) ?: '' }}</td></tr>
    </table>
    <div class="ph nota">Consulte el régimen de protección de usuarios en www.crcom.gov.co</div>

</div>
</div>

{{-- ═══════════ EVIDENCIAS ADJUNTAS A LA ASIGNACIÓN (documento e imgA..H) ═══
     No son parte del modelo de la CRC: son los soportes que carga el técnico y
     que este sistema ya venía anexando al final del contrato. --}}
@php
    $imagenes = [];
    if($digital->documento ?? null) $imagenes[] = ['letra' => 'Documento', 'ruta' => $digital->documento];
    foreach (['A','B','C','D','E','F','G','H'] as $letra) {
        $campo = 'img'.$letra;
        if($digital->$campo ?? null) $imagenes[] = ['letra' => $letra, 'ruta' => $digital->$campo];
    }
@endphp

@foreach($imagenes as $imagen)
    <div class="evidencia">
        <div class="bar first">Imagen {{ $imagen['letra'] }}</div>
        <img src="{{ contabo_url(env('ADJUNTOS_FOLDER', 'adjuntos'), $imagen['ruta']) }}" alt="Imagen {{ $imagen['letra'] }}">
    </div>
@endforeach

@endsection
