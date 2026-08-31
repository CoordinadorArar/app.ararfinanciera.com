<style>
    #logoArar{
        width: 25%;
    }
    *{
        font-family:sans-serif;
    }
    #encabezado{
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 1.5em
    }
    #tablaDatos, #tablaCupo, #tablaResultado{
        border-collapse: collapse;
    }
    th,td{
        border: solid 0.5px;
        text-align: left;
    }
    #tablaDatos, #tablaResultado, #tablaCupo, #tablaTitulo{
        width: 100%;
    }
    #tablaDatos th, #tablaResultado th, #tablaCupo th, #tablaTitulo th{
        width: 30% !important;
    }
    #tablaDatos td, #tablaResultado td, #tablaCupo td, #tablaTitulo td{
        width: 60% !important;
    }
    #tablaTitulo, #tablaTitulo th, #tablaTitulo td{
        border: none !important;
    }
    #tablaTitulo img{
        width: 250px;
    }
    #linea-firma{
        border-bottom: solid 0.5px;
        width: 250px;
    }
</style>
<div id="encabezado">
    <table id="tablaTitulo">
        <tr>
            <th><img src="{{ asset('images/LogoArar.png') }}" alt="" id="logoArar"></th>
            <td><h4>FORMATO ESTUDIO DE CRÉDITO LIBRANZAS</h4></td>
        </tr>
    </table>
</div><br><br>
<table id="tablaDatos">
    <tbody>
        @foreach($proceso as $data)
            <tr>
                <th>Nombre del cliente</th>
                <td>{{ $data->NombresTercero }} {{ $data->ApellidosTercero }}</td>
            </tr>
            <tr>
                <th>Identificación</th>
                <td>{{ $data->DocumentoTercero }}</td>
            </tr>
            <tr>
                <th>Fecha de nacimiento</th>
                <td>{{ $data->FechaNacimientoTercero }}</td>
            </tr>
            <tr>
                <th>Pagador</th>
                <td>{{ $data->NombrePagaduria }}</td>
            </tr>
            <tr>
                <th>Domicilio</th>
                <td>{{ $data->NombreMunicipio }}, {{ $data->NombreDepartamento }}</td>
            </tr>
            <tr>
                <th>Asesor Externo</th>
                <td>{{ strtoupper($data->nombreUsuario) }}</td>
            </tr>
            <tr>
                <th>SARLAFT</th>
                <td></td>
            </tr>
            <tr>
                <th>Correo Electrónico</th>
                <td>{{ $data->EmailTercero }}</td>
            </tr>
            <tr>
                <th>Teléfono</th>
                <td>{{ $data->TelefonoTercero }}</td>
            </tr>
        @endforeach
    </tbody>
</table><hr><br>
<h4>Análisis libranza</h4>
<table id="tablaCupo">
    <tbody>
        @foreach($proceso as $data)
            <tr>
                <th>Valor crédito solicitado</th>
                <td>$ {{ number_format($data->ValorCreditoSolicitado,0,'',',') }}</td>
            </tr>
            <tr>
                <th>Cuotas</th>
                <td>{{ $data->NumeroCuotas }}</td>
            </tr>
            <tr>
                <th>Valor Cuota</th>
                <td>$ {{ number_format($data->ValorCuota,0,'',',') }}</td>
            </tr>
            <tr>
                <th>Tasa de interés</th>
                <td>{{ $data->TasaInteres }} %</td>
            </tr>
            <tr>
                <th>Configuración cupo {{ $data->NombrePagaduria }}</th>
                <td>{{ $data->Configuracion }}</td>
            </tr>
            <tr>
                <th>Operación realizada</th>
                <td>{{ $data->ValoresOperacion }}</td>
            </tr>
            <tr>
                <th>Cupo aprobado</th>
                <td>$ {{ number_format($data->CupoDisponible,0,'',',') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<hr>
<table id="tablaResultado">
    <tbody>
        @foreach($proceso as $data)
            <tr>
                <th>Resultado: </th>
                <td>
                    @if($data->EstadoProceso == 5)
                        <h3>Aprobado</h3>
                    @elseif($data->EstadoProceso == 0)
                        <h3>Rechazado</h3>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
<br><br><br><br>
<br>
<div id="firmas">
    <div>
        <div id="linea-firma"></div>
        <div id="linea-firma" style="float:right; width:200px;"></div><br>
        <span>Firma Gerente Arar Financiera</span>
        <span style="float:right;">Fecha Aprobación </span><br>
        <span><strong>Maria Elena Olmos Rosales</strong></span>
    </div>
</div>