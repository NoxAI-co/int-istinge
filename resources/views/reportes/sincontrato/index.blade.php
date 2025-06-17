@extends('layouts.app')
@section('content')


    <form id="form-reporte">

        <div class="row card-description">
            <div class="form-group col-md-4" style=" padding-top: 24px;">
                <button type="button" id="exportar" class="btn btn-outline-success">Exportar a Excel</button>
            </div>
        </div>

        <input type="hidden" name="orderby"id="order_by"  value="2">
        <input type="hidden" name="order" id="order" value="1">
        <input type="hidden" id="form" value="form-reporte">

        <div class="row card-description">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-hover " id="table-facturas">
                    <thead class="thead-dark">
                    <tr>
                        <th>Documento</th>
                        <th>Cliente </th>
                        <th>Telefono </th>
                        <th>Email </th>
                        <th>Deuda Actual</th>
                    </tr>
                    </thead>
                    <tbody>

                    @foreach($clientesSinContrato as $sinContrato)
                        <tr>
                            <td><a href="{{route('contactos.show',$sinContrato->id)}}" target="_blank">{{$sinContrato->nit}}</a></td>
                            <td>{{ $sinContrato->nombre }}</td>
                            <td>{{ $sinContrato->telefono1 }}</td>
                            <td>{{ $sinContrato->email }}</td>
                            <td>{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($sinContrato->saldoDebe)}}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="thead-dark">
                    <th  colspan="4" class="text-right">Total</th>
                    <th>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($saldoTotal)}}</th>
                    </tfoot>

                </table>
                {!! $clientesSinContrato->render() !!}
            </div>
        </div>
    </form>
    {{-- <input type="hidden" id="urlgenerar" value="{{route('reportes.facturasEstandar')}}"> --}}
    <input type="hidden" id="urlexportar" value="{{route('exportar.personasincontrato')}}">
@endsection
