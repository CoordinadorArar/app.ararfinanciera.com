<?php

namespace App\Http\Controllers;

use App\Models\GestionDocumental;
use Illuminate\Http\Request;

class GestionDocumentalController extends Controller
{
    /**Servir el PDF escaneado de un folio solo a usuarios autenticados.
     * La carpeta project/folios/ está bloqueada a acceso directo por .htaccess;
     * este es el único camino para verla, y solo si se tiene sesión iniciada. */
    public function verDocumentoFolio($idFolio)
    {
        if (!preg_match('/^[0-9]+$/', $idFolio)) {
            abort(404);
        }

        $ruta = base_path('folios/'.$idFolio.'/'.$idFolio.'_0.pdf');

        if (!is_file($ruta)) {
            abort(404);
        }

        return response()->file($ruta, ['Content-Type' => 'application/pdf']);
    }

    /**Buscar folios por cliente, folio o factura y traer la información completa de cada uno (cliente + facturas) */
    public function consultarFolios(Request $request)
    {
        $request->validate([
            'tipoBusqueda' => 'required|in:cliente,folio,factura',
            'valorBusqueda' => 'required|string|max:50',
        ]);

        $tipo = $request->input('tipoBusqueda');
        $valor = trim($request->input('valorBusqueda'));

        switch ($tipo) {
            case 'cliente':
                $encontrados = GestionDocumental::buscarFoliosPorCliente($valor);
                break;
            case 'folio':
                $encontrados = GestionDocumental::buscarFoliosPorFolio($valor);
                break;
            default:
                $encontrados = GestionDocumental::buscarFoliosPorFactura($valor);
                break;
        }

        $resultados = [];
        foreach ($encontrados as $item) {
            $resultados[] = GestionDocumental::detalleFolio($item->idfolio);
        }

        return response()->json(['resultados' => $resultados]);
    }

    /* =====================  Digitalización de facturas  ===================== */

    /**Listar facturas (operaciones) pendientes de digitalizar */
    public function facturasPendientesDigitalizar()
    {
        return response()->json(['facturas' => GestionDocumental::facturasPendientesDigitalizar()]);
    }

    /**Estadísticas del tablero: facturas pendientes de digitalizar por asesor y por mes */
    public function estadisticasDigitalizar()
    {
        return response()->json([
            'porAsesor' => GestionDocumental::pendientesDigitalizarPorAsesor(),
            'porMes' => GestionDocumental::pendientesDigitalizarPorMes(),
        ]);
    }

    /**Listar cajas pendientes de ubicar (estado=0), para el selector del formulario de digitalización */
    public function cajasEnProceso()
    {
        return response()->json(['cajas' => GestionDocumental::cajasEnProceso()]);
    }

    /**Listar cajas pendientes de ubicar */
    public function cajasPendientesUbicacion()
    {
        return response()->json(['cajas' => GestionDocumental::cajasPendientesUbicacion()]);
    }

    /**Crear una caja nueva */
    public function crearCaja(Request $request)
    {
        $request->validate(['nombre' => 'required|string|max:12']);
        $idCaja = GestionDocumental::crearCaja($request->input('nombre'));
        return response()->json(['success' => true, 'idCaja' => $idCaja]);
    }

    /**Digitalizar una factura: crea el folio, sus relaciones y guarda el PDF subido */
    public function digitalizarFactura(Request $request)
    {
        $request->validate([
            'factura' => 'required|string|max:30',
            'idCliente' => 'required|string|max:30',
            'idCaja' => 'nullable|integer',
            'nombreCajaNueva' => 'nullable|string|max:12',
            'archivo' => 'required|file|mimes:pdf|max:20480',
        ]);

        if (!$request->filled('idCaja') && !$request->filled('nombreCajaNueva')) {
            return response()->json(['success' => false, 'message' => 'Debes seleccionar una caja o indicar el nombre de una caja nueva.'], 422);
        }

        $usuario = auth()->id();
        $idCaja = $request->input('idCaja');

        if (!$idCaja) {
            $idCaja = GestionDocumental::crearCaja($request->input('nombreCajaNueva'));
        }

        $idFolio = GestionDocumental::digitalizarFactura(
            $request->input('factura'),
            $request->input('idCliente'),
            $idCaja,
            $usuario
        );

        $carpeta = base_path('folios/'.$idFolio);
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }
        $nombreArchivo = $idFolio.'_0.pdf';
        $request->file('archivo')->move($carpeta, $nombreArchivo);

        // Se guarda la ruta autenticada (no la ruta pública directa): project/folios/ está bloqueada por .htaccess.
        $urlDocumento = route('ver-documento-folio', ['idFolio' => $idFolio]);
        GestionDocumental::registrarDocumentoEscaneado($idFolio, $request->input('factura'), $urlDocumento, $usuario);

        return response()->json(['success' => true, 'idFolio' => $idFolio, 'folio' => 'EMP'.$idFolio]);
    }

    /**Asignar la ubicación física final de una caja (estado 0 -> 1, Ubicada) */
    public function ubicarCaja(Request $request)
    {
        $request->validate([
            'idCaja' => 'required|integer',
            'idPiso' => 'required|integer',
            'idPasillo' => 'required|integer',
            'idEstante' => 'required|integer',
            'idPosicion' => 'required|integer',
            'idColumna' => 'required|integer',
            'idFila' => 'required|integer',
        ]);

        GestionDocumental::ubicarCaja(
            $request->input('idCaja'),
            $request->input('idPiso'),
            $request->input('idPasillo'),
            $request->input('idEstante'),
            $request->input('idPosicion'),
            $request->input('idColumna'),
            $request->input('idFila')
        );

        return response()->json(['success' => true]);
    }
}
