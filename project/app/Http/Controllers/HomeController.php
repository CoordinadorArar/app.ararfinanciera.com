<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Procesos;
use App\Models\Terceros;
use App\Models\User;
use App\Services\FlujoProceso;
use Illuminate\Http\Request;
use Throwable;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function inicioResumen()
    {
        $usuario = auth()->user();
        $idUsuario = auth()->id();
        $flujo = FlujoProceso::instancia();
        $roles = FlujoProceso::rolesUsuario($idUsuario);
        $rol = User::obtenerRol($idUsuario);
        $idRol = $rol ? (int) $rol[0]->IdRol : null;
        $orden = array_flip(array_map(function ($menu) {
            return (int) $menu->IdMenu;
        }, Admin::obtenerMenus()));
        $submenus = $idRol ? Admin::obtenerSubMenus($idRol) : [];
        usort($submenus, function ($a, $b) use ($orden) {
            return [$orden[(int) $a->IdMenu] ?? PHP_INT_MAX, (int) $a->IdSubmenu] <=> [$orden[(int) $b->IdMenu] ?? PHP_INT_MAX, (int) $b->IdSubmenu];
        });
        $rutas = array_column($submenus, 'RutaSubmenu');
        $kpis = [];
        $pendientes = [];
        $pendientesTotal = 0;
        if (in_array('/lista-procesos', $rutas, true)) {
            $resumen = Procesos::resumenInicio($idUsuario, $roles, $flujo);
            $estados = array_keys($resumen['contadores']);
            usort($estados, function ($a, $b) {
                return [$a === 0, $a] <=> [$b === 0, $b];
            });
            foreach ($estados as $estado) {
                $kpis[] = ['clave' => 'procesos-estado-'.$estado, 'titulo' => $flujo->nombreEstado($estado), 'valor' => $resumen['contadores'][$estado], 'estado' => $estado, 'url' => url('/lista-procesos').'?estado='.$estado];
            }
            foreach ($resumen['estadosPendientes'] as $estado) {
                $pendientesTotal += $resumen['contadores'][$estado];
            }
            foreach ($resumen['pendientes'] as $fila) {
                $pendientes[] = [
                    'idProceso' => (int) $fila->IdProceso,
                    'cliente' => trim($fila->NombresTercero.' '.$fila->ApellidosTercero),
                    'estado' => (int) $fila->EstadoProceso,
                    'estadoNombre' => $flujo->nombreEstado($fila->EstadoProceso),
                    'fecha' => $fila->Fecha,
                    'accion' => $flujo->accionPrincipal($fila->EstadoProceso, $roles),
                    'url' => url('/lista-procesos').'?proceso='.$fila->IdProceso,
                ];
            }
        }
        if (in_array('/crear-cliente-siesa', $rutas, true)) {
            try {
                $siesa = Terceros::listadoClientesSiesa('', 'pendiente', 1, 1);
                $kpis[] = ['clave' => 'clientes-siesa-pendientes', 'titulo' => 'Clientes pendientes en SIESA', 'valor' => $siesa['contadores']['pendiente'], 'url' => url('/crear-cliente-siesa')];
            } catch (Throwable $e) {
                report($e);
            }
        }
        $accesos = [];
        foreach ($submenus as $submenu) {
            if (count($accesos) < 6 && !isset($accesos[$submenu->RutaSubmenu]) && !in_array($submenu->RutaSubmenu, ['/log-out', '/perfil-usuario'], true)) {
                $accesos[$submenu->RutaSubmenu] =['titulo' => $submenu->NombreSubmenu, 'url' => url($submenu->RutaSubmenu), 'icono' => preg_match('/class="([^"]+)"/', (string) $submenu->CodigoSubmenu, $icono) ? $icono[1] : null];
            }
        }
        return response()->json([
            'usuario' => ['nombre' => $usuario->nombreUsuario, 'rol' => $idRol, 'rolNombre' => Procesos::rolesUsuarios([$idUsuario])[$idUsuario] ?? null],
            'kpis' => $kpis,
            'pendientes' => $pendientes,
            'pendientesTotal' => $pendientesTotal,
            'accesos' => array_values($accesos),
        ]);
    }
}
