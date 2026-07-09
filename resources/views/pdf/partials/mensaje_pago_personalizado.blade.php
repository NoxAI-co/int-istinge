{{--
    Hoja informativa de puntos/medios de pago y horario.
    Se muestra SOLO para la empresa con NIT 805030547 (Redes TV Sat), como una
    hoja adicional después de cada factura en la impresión masiva de PDFs.
    El @if gatea también los saltos de página que la envuelven en la plantilla,
    de modo que para las demás empresas no se genera ninguna hoja en blanco.
--}}
@php
    $empresaPdf   = Auth::check() ? Auth::user()->empresa() : (isset($empresa) ? $empresa : null);
    $nombreEmpPdf = $empresaPdf->nombre ?? 'REDES TV SAT';
@endphp

<div style="font-family: 'DejaVu Sans', sans-serif; color: #1f2d3d; padding: 6px 10px;">

    {{-- Encabezado --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px;">
        <tr>
            <td style="border-bottom: 3px solid #1A59A1; padding-bottom: 8px;">
                <div style="font-size: 16px; font-weight: bold; color: #1A59A1; letter-spacing: 0.5px;">
                    INFORMACIÓN DE PAGO
                </div>
                <div style="font-size: 10px; color: #6b7885; margin-top: 2px;">
                    {{ $nombreEmpPdf }} &nbsp;·&nbsp; NIT 805030547
                </div>
            </td>
        </tr>
    </table>

    {{-- PUNTOS DE PAGO --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="background-color: #1A59A1; color: #ffffff; font-size: 11px; font-weight: bold; padding: 6px 10px;">
                PUNTOS DE PAGO AUTORIZADOS
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #d7dee6; border-top: none; padding: 10px 12px; font-size: 10.5px; line-height: 16px;">
                <div style="margin-bottom: 6px; color: #48576a;">
                    Los pagos <b>solo deben realizarse directamente</b> en las siguientes oficinas:
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 3%; vertical-align: top; color: #1A59A1; font-weight: bold;">•</td>
                        <td style="padding-bottom: 5px;">
                            <b>MORICHAL</b> — CL 54 No 42 C1-11<br>
                            <span style="color: #6b7885;">Quejas, reclamos y soporte técnico · Cel. 321 648 6577</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 3%; vertical-align: top; color: #1A59A1; font-weight: bold;">•</td>
                        <td style="padding-bottom: 5px;">
                            <b>OFICINA REPÚBLICA ISRAEL</b> — CL 40 No 43 B34-34<br>
                            <span style="color: #6b7885;">Cel. 324 405 0311</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 3%; vertical-align: top; color: #1A59A1; font-weight: bold;">•</td>
                        <td>
                            <b>PUNTO PAGO LLANO VERDE</b> — KR 56D No 48B-77<br>
                            <span style="color: #6b7885;">Cel. 318 817 4032</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- MEDIOS DE PAGO --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="background-color: #1A59A1; color: #ffffff; font-size: 11px; font-weight: bold; padding: 6px 10px;">
                MEDIOS DE PAGO · CUENTAS AUTORIZADAS
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #d7dee6; border-top: none; padding: 10px 12px; font-size: 10.5px; line-height: 16px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 3%; vertical-align: top; color: #1A59A1; font-weight: bold;">•</td>
                        <td style="padding-bottom: 5px;">
                            <b>BANCOLOMBIA</b> — Cuenta de Ahorros No. 062 00006179<br>
                            <span style="color: #6b7885;">A nombre de REDES TV SAT · Ref: NIT 805030547</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 3%; vertical-align: top; color: #1A59A1; font-weight: bold;">•</td>
                        <td style="padding-bottom: 5px;">
                            <b>NEQUI</b> — 302 716 7305<br>
                            <span style="color: #6b7885;">A nombre de ALEXANDER CONTRERAS</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 3%; vertical-align: top; color: #1A59A1; font-weight: bold;">•</td>
                        <td>
                            Enviar el <b>soporte de pago</b> al WhatsApp <b>321 648 6577</b><br>
                            <span style="color: #6b7885;">Indicando nombre completo y número de cédula</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- HORARIO --}}
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="background-color: #1A59A1; color: #ffffff; font-size: 11px; font-weight: bold; padding: 6px 10px;">
                HORARIO DE ATENCIÓN
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #d7dee6; border-top: none; padding: 0; font-size: 10.5px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 7px 12px; border-bottom: 1px solid #eef1f5; width: 55%;"><b>Lunes a viernes</b></td>
                        <td style="padding: 7px 12px; border-bottom: 1px solid #eef1f5; color: #48576a;">8:00 a.m. – 6:00 p.m. (jornada continua)</td>
                    </tr>
                    <tr>
                        <td style="padding: 7px 12px; border-bottom: 1px solid #eef1f5;"><b>Sábados</b></td>
                        <td style="padding: 7px 12px; border-bottom: 1px solid #eef1f5; color: #48576a;">8:00 a.m. – 3:00 p.m.</td>
                    </tr>
                    <tr>
                        <td style="padding: 7px 12px;"><b>Domingos y festivos</b></td>
                        <td style="padding: 7px 12px; color: #48576a;">9:00 a.m. – 1:00 p.m.</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</div>
