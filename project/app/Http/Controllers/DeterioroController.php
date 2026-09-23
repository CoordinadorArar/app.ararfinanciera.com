<?php

namespace App\Http\Controllers;

ini_set('max_execution_time', 600);

use App\Http\Middleware\CheckDeterioroPermiso;
use App\Models\Deterioro;
use App\Support\DeterioroExportador;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoints del módulo de deterioro de cartera.
 *
 * Las pantallas de resumen devuelven HTML armado en PHP, como el resto del
 * sitio. El detalle por operación devuelve datos en JSON: son ~2.100 filas y
 * armarlas como cadena aquí produciría varios MB por respuesta.
 */
class DeterioroController extends Controller
{
    /** Periodo que tiene cargado hoy la tabla origen, para la pantalla de cortes. */
    public function periodoOrigen()
    {
        $corte = Deterioro::periodoDisponible();
        $comparacion = Deterioro::periodoDisponible(Deterioro::origenComparacion());

        return response()->json([
            'corte' => $corte,
            'comparacion' => $comparacion,
        ]);
    }

    public function listarCortes()
    {
        return response()->json(['cortes' => Deterioro::listarCortes()]);
    }

    public function crearCorte(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'fechaCorte' => 'required|date',
            'fechaComparacion' => 'nullable|date',
        ]);
        if ($validate->fails()) {
            return response()->json(['errors' => $validate->errors()]);
        }

        $fechaCorte = date('Y-m-d', strtotime($request->input('fechaCorte')));

        if (Deterioro::where('fecha_corte', $fechaCorte)->exists()) {
            return response()->json(['res' => 'bad', 'title' => 'Corte duplicado',
                'text' => 'Ya existe un corte para el '.$fechaCorte.'.']);
        }

        $validacion = Deterioro::validarPeriodoOrigen($fechaCorte);
        if (!$validacion['ok']) {
            return response()->json(['res' => 'bad', 'title' => 'El origen no corresponde',
                'text' => $validacion['mensaje']]);
        }

        $fechaComparacion = $request->input('fechaComparacion')
            ? date('Y-m-d', strtotime($request->input('fechaComparacion')))
            : null;

        $idCorte = Deterioro::crearCorte($fechaCorte, $fechaComparacion, auth()->id());
        Deterioro::registrarBitacora('CREAR_CORTE', $idCorte, null, null, $fechaCorte, auth()->id());

        return response()->json(['res' => 'ok', 'idCorte' => $idCorte,
            'title' => 'Corte creado', 'text' => 'Ahora puedes ejecutar el cálculo.']);
    }

    public function ejecutarCorte(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        try {
            $resultado = Deterioro::ejecutar($idCorte, auth()->id());
        } catch (\Throwable $e) {
            return response()->json(['res' => 'bad', 'title' => 'No se pudo calcular',
                'text' => $e->getMessage()]);
        }

        $falla = 0;
        $ok = 0;
        $nota = 0;
        foreach ($resultado['cuadres'] as $c) {
            if ($c['estado'] === 'FALLA') {
                $falla++;
            } elseif ($c['estado'] === 'N/A') {
                $nota++;
            } else {
                $ok++;
            }
        }
        $plural = function ($n, $singular, $sufijo) {
            return $n.' '.($n === 1 ? $singular : $singular.'s').' '.$sufijo;
        };
        $texto = 'Terminó en '.number_format($resultado['duracion_ms'] / 1000, 1).' segundos. '
            .($falla ? $plural($falla, 'cuadre', 'con diferencia') : $plural($ok, 'cuadre', 'en cero'))
            .($nota ? ' y '.$nota.($nota === 1 ? ' que no aplica' : ' que no aplican').' a este corte' : '').'.';

        return response()->json([
            'res' => 'ok',
            'title' => 'Corte calculado',
            'text' => $texto,
            'duracion_ms' => $resultado['duracion_ms'],
            'pasos' => $resultado['pasos'],
            'cuadres' => $resultado['cuadres'],
        ]);
    }

    public function eliminarCorte(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $corte = Deterioro::corte($idCorte);
        if (!$corte) {
            return response()->json(['res' => 'bad', 'title' => 'No encontrado', 'text' => 'El corte no existe.']);
        }
        if ($corte->estado === Deterioro::ESTADO_CERRADO) {
            return response()->json(['res' => 'bad', 'title' => 'Corte cerrado',
                'text' => 'Un corte cerrado no se puede eliminar.']);
        }

        Deterioro::eliminarCorte($idCorte);
        Deterioro::registrarBitacora('ELIMINAR_CORTE', null, null, $corte->fecha_corte, null, auth()->id());

        return response()->json(['res' => 'ok', 'title' => 'Corte eliminado',
            'text' => 'Se borró el corte del '.$corte->fecha_corte.' y todo su detalle.']);
    }

    /** Matriz producto x rango, réplica del bloque T14:AB27 del libro. */
    public function resumenCorte(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $corte = Deterioro::corte($idCorte);
        if (!$corte) {
            return response()->json(['res' => 'bad', 'text' => 'El corte no existe.']);
        }

        return response()->json([
            'res' => 'ok',
            'corte' => $corte,
            'resumen' => Deterioro::resumenPorProductoRango($idCorte),
            'cuadres' => Deterioro::cuadres($idCorte),
        ] + $this->exportables($idCorte));
    }

    /** Totales fiscales por rango: individual, general, acumulado, tope y deducción del año. */
    public function resumenFiscal(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $corte = Deterioro::corte($idCorte);
        if (!$corte) {
            return response()->json(['res' => 'bad', 'text' => 'El corte no existe.']);
        }

        return response()->json([
            'res' => 'ok',
            'corte' => $corte,
            'resumen' => Deterioro::resumenFiscal($idCorte),
            'cuadres' => Deterioro::cuadres($idCorte),
        ]);
    }

    /**
     * Comparativo contable contra fiscal e impuesto diferido (§11). Devuelve
     * los cuatro bloques de la pantalla en una sola llamada.
     */
    public function comparativoContableFiscal(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $corte = Deterioro::corte($idCorte);
        if (!$corte) {
            return response()->json(['res' => 'bad', 'text' => 'El corte no existe.']);
        }

        $corte->operaciones_topadas = Deterioro::operacionesTopadas($idCorte);

        return response()->json([
            'res' => 'ok',
            'corte' => $corte,
            'tarifaRenta' => Deterioro::tarifaRenta($idCorte),
            'resumen' => Deterioro::resumenPorProductoRango($idCorte),
            'movimiento' => Deterioro::puenteMovimiento($idCorte),
            'evolucion' => Deterioro::evolucionDiferenciaTemporaria($idCorte),
            'proyeccion' => Deterioro::proyeccionReversion($idCorte),
            'cuadres' => Deterioro::cuadres($idCorte),
        ] + $this->exportables($idCorte));
    }

    /**
     * Histórico y descomposición del movimiento del mes (RN-10). Devuelve los
     * bloques de la pantalla en una sola llamada, incluidas las bajas, que se
     * detectan y se muestran pero no se clasifican: eso es de la fase 7.
     */
    public function evolucionHistorica(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $corte = Deterioro::corte($idCorte);
        if (!$corte) {
            return response()->json(['res' => 'bad', 'text' => 'El corte no existe.']);
        }

        return response()->json([
            'res' => 'ok',
            'corte' => $corte,
            'serie' => Deterioro::serieHistorica($idCorte),
            'descomposicion' => Deterioro::descomposicionMovimiento($idCorte),
            'comparativo' => [
                'rango' => Deterioro::comparativoContraAnterior($idCorte, 'rango'),
                'producto' => Deterioro::comparativoContraAnterior($idCorte, 'producto'),
            ],
            'bajas' => Deterioro::bajasDelPeriodo($idCorte),
            'conciliacion' => Deterioro::conciliacionLibro($idCorte),
            'cuadres' => Deterioro::cuadres($idCorte),
        ] + $this->exportables($idCorte));
    }

    public function detalleOperaciones(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        if (!Deterioro::corte($idCorte)) {
            return response()->json(['res' => 'bad', 'text' => 'El corte no existe.']);
        }

        $filas = Deterioro::detalleOperaciones($idCorte, $this->filtrosDetalle($request));

        return response()->json(['res' => 'ok', 'operaciones' => $filas]
            + $this->exportables($idCorte));
    }

    /** Filtros de la grilla del detalle, que el exportable tiene que respetar. */
    private function filtrosDetalle(Request $request)
    {
        return [
            'producto' => $request->input('producto'),
            'rango' => $request->input('rango'),
            'soloDeterioro' => $request->input('soloDeterioro'),
            'soloDeduccion' => $request->input('soloDeduccion'),
            'soloTopadas' => $request->input('soloTopadas'),
            'soloPasivo' => $request->input('soloPasivo'),
            'soloDuplicadas' => $request->input('soloDuplicadas'),
            'busqueda' => $request->input('busqueda'),
        ];
    }

    /**
     * Lo que las siete pantallas de corte necesitan para ofrecer los
     * exportables: el permiso, resuelto con el mismo método que usa el
     * middleware y no con una copia de la regla, y la disponibilidad del
     * asiento con su motivo.
     *
     * Va la disponibilidad y no una lista de conceptos faltantes porque una
     * lista vacía no distingue «descárguelo» de «este corte no puede tener
     * asiento nunca»: las dos llegaban idénticas a la pantalla.
     */
    private function exportables($idCorte)
    {
        return [
            'permisos' => ['exportar' => CheckDeterioroPermiso::tiene('exportar')],
            'exportables' => ['asiento' => Deterioro::disponibilidadAsiento($idCorte)],
        ];
    }

    public function cuotasOperacion(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $idOperacion = (int) $request->input('idOperacion');

        return response()->json([
            'res' => 'ok',
            'cuotas' => Deterioro::cuotasDeOperacion($idCorte, $idOperacion),
        ]);
    }

    /** Partidas de la conciliación con SIESA (C-3), su resumen y los cuadres. */
    public function conciliacionSiesa(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $corte = Deterioro::corte($idCorte);
        if (!$corte) {
            return response()->json(['res' => 'bad', 'text' => 'El corte no existe.']);
        }

        return response()->json([
            'res' => 'ok',
            'corte' => $corte,
            'partidas' => Deterioro::conciliacionSiesa($idCorte, [
                'tipo' => $request->input('tipo'),
                'estado' => $request->input('estado'),
                'busqueda' => $request->input('busqueda'),
            ]),
            'resumen' => Deterioro::resumenConciliacion($idCorte),
            'cobertura' => Deterioro::coberturaConciliacion($idCorte),
            'estados' => Deterioro::ESTADOS_CONCILIACION,
            // La pantalla no dibuja lo que el servidor va a rechazar. Sale del
            // mismo método que usa el middleware, no de una copia de la regla.
            'permisos' => [
                'conciliar' => CheckDeterioroPermiso::tiene('conciliar'),
                'exportar' => CheckDeterioroPermiso::tiene('exportar'),
            ],
            'exportables' => ['asiento' => Deterioro::disponibilidadAsiento($idCorte)],
            'cuadres' => Deterioro::cuadres($idCorte),
        ]);
    }

    /**
     * Explicar una partida es un acto contable con autor, no una consulta: va
     * por el permiso propio 'conciliar'.
     */
    public function explicarPartida(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'idCorte' => 'required|integer',
            'numeroOperacion' => 'required|integer',
            'estado' => 'required|string|max:20',
            'explicacion' => 'required|string|max:500',
        ]);
        if ($validate->fails()) {
            return response()->json(['res' => 'bad', 'title' => 'Datos inválidos',
                'text' => $validate->errors()->first()]);
        }

        $resultado = Deterioro::explicarPartida(
            (int) $request->input('idCorte'),
            (int) $request->input('numeroOperacion'),
            $request->input('estado'),
            $request->input('explicacion'),
            auth()->id()
        );

        if (!$resultado['ok']) {
            return response()->json(['res' => 'bad', 'title' => 'No se pudo guardar',
                'text' => $resultado['mensaje']]);
        }

        return response()->json(['res' => 'ok', 'title' => 'Partida explicada',
            'text' => 'Se guardó la explicación de la partida '.$resultado['tipo'].'.']);
    }

    /** Lista de marcas de suspensión, con las causales para poblar el formulario. */
    public function suspensiones(Request $request)
    {
        return response()->json([
            'res' => 'ok',
            'suspensiones' => Deterioro::suspensiones([
                'estado' => $request->input('estado'),
                'busqueda' => $request->input('busqueda'),
            ]),
            'causales' => Deterioro::causalesSuspension(),
        ]);
    }

    /** Alimenta la pantalla de suspensiones de un corte: marcadas, candidatas y cuadres. */
    public function suspensionesCorte(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $corte = Deterioro::corte($idCorte);
        if (!$corte) {
            return response()->json(['res' => 'bad', 'text' => 'El corte no existe.']);
        }

        return response()->json([
            'res' => 'ok',
            'corte' => $corte,
            'suspendidas' => Deterioro::suspensionesDelCorte($idCorte),
            'candidatas' => Deterioro::candidatasSuspension($idCorte),
            'cuadres' => Deterioro::cuadres($idCorte),
        ] + $this->exportables($idCorte));
    }

    public function marcarSuspension(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'idOperacion' => 'required|integer',
            'causal' => 'required|string',
            'fechaEvento' => 'required|date|before_or_equal:today',
            'observacion' => 'required|string|max:500',
            'soporte' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:20480',
        ]);
        if ($validate->fails()) {
            return response()->json(['res' => 'bad', 'title' => 'Datos inválidos',
                'text' => $validate->errors()->first()]);
        }

        // max:255 sobre un archivo es tamaño, no longitud del nombre: el límite
        // de soporte_nombre se valida aparte para no devolver el error de
        // truncamiento de SQL Server.
        if ($request->hasFile('soporte') && mb_strlen($request->file('soporte')->getClientOriginalName()) > 255) {
            return response()->json(['res' => 'bad', 'title' => 'Datos inválidos',
                'text' => 'El nombre del archivo de soporte no puede superar 255 caracteres.']);
        }

        $fechaEvento = date('Y-m-d', strtotime($request->input('fechaEvento')));
        $resultado = Deterioro::marcarSuspension(
            (int) $request->input('idOperacion'),
            $request->input('causal'),
            $fechaEvento,
            $request->input('observacion'),
            $request->file('soporte'),
            auth()->id()
        );

        if (!$resultado['ok']) {
            return response()->json(['res' => 'bad', 'title' => 'No se pudo marcar', 'text' => $resultado['mensaje']]);
        }

        $facturas = $resultado['facturasFat'];
        $texto = $resultado['congelado']
            ? 'Se congeló el interés en '.number_format($resultado['interesCongelado'], 2, ',', '.')
                .', tomado de '.$facturas.' factura'.($facturas === 1 ? '' : 's')
                .' FAT de SIESA hasta el mes del evento.'
            : 'La operación no tiene ninguna factura FAT en SIESA hasta el mes del evento: '
                .'el interés queda sin congelar.';
        if ($resultado['corteCalculado']) {
            $texto .= ' El corte más reciente ya está calculado: la marca no lo altera hasta que se recalcule.';
        }
        if ($request->hasFile('soporte')) {
            $texto .= ' El soporte quedó adjunto a la marca.';
        }

        return response()->json([
            'res' => 'ok', 'title' => 'Suspensión registrada', 'text' => $texto,
            'idSuspension' => $resultado['idSuspension'],
            'congelado' => $resultado['congelado'],
            'interesCongelado' => $resultado['interesCongelado'],
            'facturasFat' => $resultado['facturasFat'],
        ]);
    }

    public function levantarSuspension(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'idSuspension' => 'required|integer',
            'observacion' => 'required|string|max:500',
        ]);
        if ($validate->fails()) {
            return response()->json(['res' => 'bad', 'title' => 'Datos inválidos',
                'text' => $validate->errors()->first()]);
        }

        $resultado = Deterioro::levantarSuspension(
            (int) $request->input('idSuspension'),
            $request->input('observacion'),
            auth()->id()
        );

        if (!$resultado['ok']) {
            return response()->json(['res' => 'bad', 'title' => 'No se pudo levantar', 'text' => $resultado['mensaje']]);
        }

        $texto = 'Se levantó la suspensión.';
        if ($resultado['corteCalculado']) {
            $texto .= ' El corte más reciente ya está calculado: el levantamiento no lo altera hasta que se recalcule.';
        }

        return response()->json(['res' => 'ok', 'title' => 'Suspensión levantada', 'text' => $texto]);
    }

    /**
     * Sirve el soporte adjunto de una marca de suspensión. La carpeta
     * soportes-suspension/ está bloqueada por .htaccess y este es el único
     * camino a esos documentos —actas de defunción, autos de insolvencia—, que
     * además no se pierden al levantar la marca ni al recalcular un corte.
     */
    public function verSoporteSuspension($idSuspension)
    {
        $soporte = Deterioro::soporteSuspension((int) $idSuspension);
        if (!$soporte) {
            return response()->json(['res' => 'bad', 'title' => 'Sin soporte',
                'text' => 'La marca no tiene soporte adjunto o el archivo ya no está disponible.']);
        }

        // Nombre en las dos formas de la RFC 6266: el ASCII como respaldo y
        // filename* con el original, que es el que lee el módulo. Sin él, un
        // «Acta de defunción Pérez.pdf» viaja con bytes no ASCII crudos en la
        // cabecera y algún proxy lo estropea.
        $nombre = str_replace(['"', "\r", "\n"], '', $soporte['nombre']);
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $nombre);
        return response()->file($soporte['ruta'], [
            'Content-Type' => $soporte['tipo'],
            'Content-Disposition' => 'inline; filename*=UTF-8\'\''.rawurlencode($nombre).'; filename="'.$ascii.'"',
        ]);
    }

    /**
     * Controles C-1 y C-2, requisitos de cierre y estado del corte, en una sola
     * llamada.
     *
     * Los cinco permisos salen del mismo método que usa el middleware, no de
     * una copia de la regla: la pantalla no dibuja acciones que el servidor va a
     * rechazar, y el día que la regla cambie no pueden divergir. El gate de
     * pantalla es cosmético; la seguridad sigue estando en el middleware.
     *
     * Las causales de la baja vienen del servidor y no del JS: son paramétrica
     * con vigencias, congelada por corte.
     */
    public function controlesCorte(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $corte = Deterioro::corte($idCorte);
        if (!$corte) {
            return response()->json(['res' => 'bad', 'text' => 'El corte no existe.']);
        }

        return response()->json([
            'res' => 'ok',
            'corte' => $corte,
            'prorrogas' => Deterioro::prorrogasQueReducenMora($idCorte),
            'bajas' => Deterioro::bajasDelPeriodo($idCorte),
            'descomposicionBajas' => Deterioro::descomposicionBajas($idCorte),
            'causales' => Deterioro::causalesSalida($idCorte),
            'cierre' => Deterioro::condicionesCierre($idCorte),
            'cuadres' => Deterioro::cuadres($idCorte),
            'permisos' => [
                'cerrar' => CheckDeterioroPermiso::tiene('cerrar'),
                'forzarCierre' => CheckDeterioroPermiso::tiene('forzarCierre'),
                'reabrir' => CheckDeterioroPermiso::tiene('reabrir'),
                'clasificarBaja' => CheckDeterioroPermiso::tiene('clasificar'),
                'auditar' => CheckDeterioroPermiso::tiene('auditar'),
                'exportar' => CheckDeterioroPermiso::tiene('exportar'),
            ],
            'exportables' => ['asiento' => Deterioro::disponibilidadAsiento($idCorte)],
        ]);
    }

    /** Clasificar una baja es un acto contable con autor: permiso propio. */
    public function clasificarSalida(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'idCorte' => 'required|integer',
            'idOperacion' => 'required|integer',
            'clasificacion' => 'required|string|max:24',
            'observacion' => 'required|string|max:500',
            'referencia' => 'nullable|string|max:30',
        ]);
        if ($validate->fails()) {
            return response()->json(['res' => 'bad', 'title' => 'Datos inválidos',
                'text' => $validate->errors()->first()]);
        }

        $resultado = Deterioro::clasificarSalida(
            (int) $request->input('idCorte'),
            (int) $request->input('idOperacion'),
            $request->input('clasificacion'),
            $request->input('observacion'),
            auth()->id(),
            $request->input('referencia')
        );

        if (!$resultado['ok']) {
            return response()->json(['res' => 'bad', 'title' => 'No se pudo clasificar',
                'text' => $resultado['mensaje']]);
        }

        $texto = 'Se clasificó la salida de la operación.';
        if ($resultado['fiscalAcumuladoCerrado'] !== null) {
            $texto .= ' Cierra deducción fiscal acumulada por '
                . number_format($resultado['fiscalAcumuladoCerrado'], 2, ',', '.').'.';
        }

        return response()->json(['res' => 'ok', 'title' => 'Baja clasificada', 'text' => $texto]);
    }

    /**
     * Cierra el corte. Sin motivo, sólo cierra si no hay condiciones que lo
     * bloqueen, y devuelve la enumeración de lo que falta cuando las hay.
     *
     * Forzar el cierre con salvedad exige un permiso propio, distinto del de
     * cerrar: la ruta pasa por 'cerrar' y aquí se exige además 'forzarCierre'.
     * Se aborta con 403 y no con un JSON de error para que la falta de permiso
     * se vea igual que en el resto del módulo.
     */
    public function cerrarCorte(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'idCorte' => 'required|integer',
            'motivoSalvedad' => 'nullable|string|max:500',
        ]);
        if ($validate->fails()) {
            return response()->json(['res' => 'bad', 'title' => 'Datos inválidos',
                'text' => $validate->errors()->first()]);
        }

        $motivo = trim((string) $request->input('motivoSalvedad'));
        if ($motivo !== '') {
            $permiso = CheckDeterioroPermiso::evaluar('forzarCierre', auth()->id());
            if (!$permiso['ok']) {
                abort(403, $permiso['mensaje']);
            }
        }

        try {
            $resultado = Deterioro::cerrarCorte((int) $request->input('idCorte'), auth()->id(), $motivo);
        } catch (\Throwable $e) {
            return response()->json(['res' => 'bad', 'title' => 'No se pudo cerrar', 'text' => $e->getMessage()]);
        }

        if (!$resultado['ok']) {
            return response()->json(['res' => 'bad', 'title' => 'No se pudo cerrar',
                'text' => $resultado['mensaje'],
                'bloqueos' => isset($resultado['bloqueos']) ? $resultado['bloqueos'] : []]);
        }

        $texto = $resultado['conSalvedad']
            ? 'El corte quedó cerrado CON SALVEDADES y así queda marcado de forma permanente.'
            : 'El corte quedó cerrado. Desde ahora ninguna escritura del módulo lo toca.';
        if ($resultado['acumuladoFiscal']) {
            $a = $resultado['acumuladoFiscal'];
            $texto .= ' Cierre de diciembre: se registró el acumulado fiscal del año gravable '
                . $a['ano'].' de '.$a['operaciones'].' operación(es) por '
                . number_format($a['valor'], 2, ',', '.').'.';
        }

        return response()->json(['res' => 'ok', 'title' => 'Corte cerrado', 'text' => $texto,
            'conSalvedad' => $resultado['conSalvedad'],
            'acumuladoFiscal' => $resultado['acumuladoFiscal']]);
    }

    /** Reabrir un corte cerrado: permiso propio, distinto del de cerrar. */
    public function reabrirCorte(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'idCorte' => 'required|integer',
            'motivo' => 'required|string|max:500',
        ]);
        if ($validate->fails()) {
            return response()->json(['res' => 'bad', 'title' => 'Datos inválidos',
                'text' => $validate->errors()->first()]);
        }

        $resultado = Deterioro::reabrirCorte(
            (int) $request->input('idCorte'), auth()->id(), $request->input('motivo'));

        if (!$resultado['ok']) {
            return response()->json(['res' => 'bad', 'title' => 'No se pudo reabrir',
                'text' => $resultado['mensaje']]);
        }

        $texto = 'El corte quedó reabierto en estado calculado.';
        if ($resultado['acumuladoRevertido']) {
            $texto .= ' Se revirtieron '.$resultado['acumuladoRevertido']
                . ' fila(s) del acumulado fiscal que había escrito su cierre.';
        }

        return response()->json(['res' => 'ok', 'title' => 'Corte reabierto', 'text' => $texto]);
    }

    /* -----------------------------------------------------------------
     | Exportables (§16)
     |---------------------------------------------------------------- */

    /**
     * Corte sobre el que se puede exportar. Se ofrecen también sobre un corte
     * CALCULADO —Contabilidad necesita el asiento para preparar el registro
     * antes de cerrar— y el archivo dice que es preliminar; sobre uno ABIERTO no
     * hay nada que exportar.
     *
     * Un rechazo por razón de negocio se devuelve como JSON con el patrón del
     * módulo y no como página de error: la descarga se pide por fetch y el
     * cliente distingue por Content-Type. Un rechazo en HTML se le descargaría
     * al usuario como un archivo roto.
     */
    private function corteParaExportar($idCorte)
    {
        $corte = Deterioro::corte($idCorte);
        if (!$corte) {
            return ['corte' => null, 'error' => response()->json(['res' => 'bad',
                'title' => 'No encontrado', 'text' => 'El corte no existe.'])];
        }
        if ($corte->estado === Deterioro::ESTADO_ABIERTO) {
            return ['corte' => null, 'error' => response()->json(['res' => 'bad',
                'title' => 'El corte no está calculado',
                'text' => 'Hay que ejecutar el cálculo antes de exportar.'])];
        }
        return ['corte' => $corte, 'error' => null];
    }

    /** Excel con las hojas de resultado del libro, para la marcha en paralelo. */
    public function exportarTransicion(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $validacion = $this->corteParaExportar($idCorte);
        if ($validacion['error']) {
            return $validacion['error'];
        }

        // El libro replica la estructura del de Contabilidad y se detiene si el
        // corte ya no cabe en ella, en vez de omitir columnas en silencio. Eso
        // es una razón de negocio y sale como JSON, no como página de error.
        try {
            $libro = DeterioroExportador::libroTransicion($idCorte);
        } catch (\RuntimeException $e) {
            return response()->json(['res' => 'bad',
                'title' => 'No se puede generar el Excel de transición', 'text' => $e->getMessage()]);
        }

        return DeterioroExportador::descargar($libro,
            DeterioroExportador::nombreArchivo('transicion', $validacion['corte'], 'xlsx'));
    }

    /** PDF del resumen del corte, con su estado y sus cuadres. */
    public function exportarResumen(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $validacion = $this->corteParaExportar($idCorte);
        if ($validacion['error']) {
            return $validacion['error'];
        }

        $pdf = Pdf::loadView('deterioro.export-resumen', [
            'corte' => $validacion['corte'],
            'resumen' => Deterioro::resumenPorProductoRango($idCorte),
            'comparativo' => Deterioro::comparativoContraAnterior($idCorte, 'rango'),
            'movimiento' => Deterioro::descomposicionMovimiento($idCorte),
            'cuadres' => Deterioro::cuadres($idCorte),
        ])->setPaper('letter', 'landscape');

        return $pdf->download(
            DeterioroExportador::nombreArchivo('resumen', $validacion['corte'], 'pdf'));
    }

    /**
     * Archivo plano del asiento (§17). Si el corte no tiene cuentas resueltas no
     * se genera: un asiento con cuentas en blanco que alguien suba a
     * contabilidad es peor que un error, así que se devuelve el rechazo con los
     * conceptos que faltan.
     */
    public function exportarAsiento(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $validacion = $this->corteParaExportar($idCorte);
        if ($validacion['error']) {
            return $validacion['error'];
        }

        $asiento = Deterioro::asientoContable($idCorte);
        if (!$asiento['ok']) {
            // Se devuelve la misma estructura de disponibilidad que viaja en las
            // pantallas, para que el cliente no tenga que leer dos contratos.
            return response()->json(['res' => 'bad', 'title' => 'No se puede generar el asiento',
                'text' => $asiento['mensaje'],
                'exportables' => ['asiento' => [
                    'disponible' => false,
                    'motivo' => $asiento['motivo'],
                    'mensaje' => $asiento['mensaje'],
                    'faltanCuentas' => $asiento['faltanCuentas'],
                ]]]);
        }

        $nombre = DeterioroExportador::nombreArchivo('asiento', $validacion['corte'], 'csv');

        return response(DeterioroExportador::planoAsiento($asiento['filas']), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /** Detalle por operación con los mismos filtros que acepta la grilla. */
    public function exportarDetalle(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        $validacion = $this->corteParaExportar($idCorte);
        if ($validacion['error']) {
            return $validacion['error'];
        }

        $filtros = $this->filtrosDetalle($request);

        return DeterioroExportador::descargar(
            DeterioroExportador::libroDetalle($idCorte, $filtros),
            DeterioroExportador::nombreArchivo('detalle', $validacion['corte'], 'xlsx',
                DeterioroExportador::sufijosDetalle($filtros)));
    }

    /**
     * Consulta de la bitácora. Las acciones y los usuarios del filtro salen de
     * lo que la bitácora tiene de verdad, no de un catálogo escrito en el
     * cliente que quedaría desactualizado con cada fase.
     */
    public function bitacora(Request $request)
    {
        $resultado = Deterioro::bitacora([
            'idCorte' => $request->input('idCorte'),
            'accion' => $request->input('accion'),
            'idUsuario' => $request->input('idUsuario'),
            'desde' => $request->input('desde'),
            'hasta' => $request->input('hasta'),
            'busqueda' => $request->input('busqueda'),
        ]);

        return response()->json([
            'res' => 'ok',
            'eventos' => $resultado['eventos'],
            'total' => $resultado['total'],
            'limite' => $resultado['limite'],
            'filtros' => Deterioro::filtrosBitacora(),
        ]);
    }
}
