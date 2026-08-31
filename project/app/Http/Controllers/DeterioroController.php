<?php

namespace App\Http\Controllers;

ini_set('max_execution_time', 600);

use App\Models\Deterioro;
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
        $corte = Deterioro::periodoDisponible(Deterioro::ORIGEN_CORTE);
        $comparacion = Deterioro::periodoDisponible(Deterioro::ORIGEN_COMPARACION);

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

        $fallas = 0;
        foreach ($resultado['cuadres'] as $c) {
            if ($c['estado'] !== 'OK') {
                $fallas++;
            }
        }

        return response()->json([
            'res' => 'ok',
            'title' => 'Corte calculado',
            'text' => 'Terminó en '.number_format($resultado['duracion_ms'] / 1000, 1)
                .' segundos. '.($fallas ? $fallas.' cuadres con diferencia.' : 'Todos los cuadres en cero.'),
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

        Deterioro::limpiarCorte($idCorte);
        Deterioro::where('id_corte', $idCorte)->delete();
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
        ]);
    }

    public function detalleOperaciones(Request $request)
    {
        $idCorte = (int) $request->input('idCorte');
        if (!Deterioro::corte($idCorte)) {
            return response()->json(['res' => 'bad', 'text' => 'El corte no existe.']);
        }

        $filas = Deterioro::detalleOperaciones($idCorte, [
            'producto' => $request->input('producto'),
            'rango' => $request->input('rango'),
            'soloDeterioro' => $request->input('soloDeterioro'),
            'busqueda' => $request->input('busqueda'),
        ]);

        return response()->json(['res' => 'ok', 'operaciones' => $filas]);
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
}
