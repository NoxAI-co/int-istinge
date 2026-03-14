<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Auditoría DIAN</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 10pt; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #ed1c24; padding-bottom: 10px; }
        .title { font-size: 16pt; font-weight: bold; color: #ed1c24; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th { background-color: #f2f2f2; border: 1px solid #ddd; padding: 8px; text-align: left; }
        .data-table td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        .text-danger { color: #dc3545; }
        .text-success { color: #28a745; }
        .footer { position: fixed; bottom: 0; width: 100%; font-size: 8pt; text-align: center; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">REPORTE DE AUDITORÍA DIAN</div>
        <div>{{ $empresa->nombre }}</div>
        <div>NIT: {{ $empresa->nit }}</div>
    </div>

    <table class="info-table">
        <tr>
            <td><b>Sesión ID:</b> #{{ $session->id }}</td>
            <td><b>Período:</b> {{ $session->periodo }}</td>
        </tr>
        <tr>
            <td><b>Fecha Carga:</b> {{ $session->created_at->format('d/m/Y H:i') }}</td>
            <td><b>Generado Por:</b> {{ Auth::user()->nombres }}</td>
        </tr>
    </table>

    <h3>Resumen de Discrepancias y Correcciones</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Folio</th>
                <th>Fecha Emisión</th>
                <th>Receptor según DIAN</th>
                <th>Receptor en Sistema</th>
                <th>Total</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($session->records->whereIn('status', ['discrepancy', 'corrected']) as $record)
            <tr>
                <td>{{ $record->folio }}</td>
                <td>{{ $record->fecha_emision }}</td>
                <td>{{ $record->nit_receptor_dian }}<br>{{ $record->nombre_receptor_dian }}</td>
                <td>
                    @if($record->status == 'corrected')
                        <span class="text-success">{{ $record->nit_receptor_sistema }}<br>{{ $record->nombre_receptor_sistema }}</span>
                    @else
                        <span class="text-danger">{{ $record->nit_receptor_sistema }}<br>{{ $record->nombre_receptor_sistema }}</span>
                    @endif
                </td>
                <td>{{ $record->total_formatted }}</td>
                <td>{{ $record->status == 'corrected' ? 'CORREGIDO' : 'PENDIENTE' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generado automáticamente por el módulo de Auditoría DIAN - Versión 1.0 - {{ date('d/m/Y H:i:s') }}
    </div>
</body>
</html>
