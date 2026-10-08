<?php

namespace App\Services;

use App\Models\Procesos;
use App\Services\Centrales\CentralesRiesgo;
use Illuminate\Support\Facades\DB;

class FlujoProceso
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function instancia()
    {
        return new self(config('procesos'));
    }

    public static function rolesUsuario($idUsuario = null)
    {
        return DB::connection('identidad')->table('RolUsuario')->where('IdUsuario', $idUsuario ?: auth()->id())
            ->pluck('IdRol')->map(function ($rol) {
                return (int) $rol;
            })->all();
    }

    public function nombreEstado($estado)
    {
        return $estado === null ? null : ($this->config['estados'][(int) $estado] ?? 'Estado '.$estado);
    }

    public function estados()
    {
        return $this->config['estados'];
    }

    private function esAdministrador(array $roles)
    {
        return in_array($this->config['administrador'], $roles);
    }

    private function tieneRol(array $permitidos, array $roles)
    {
        return $this->esAdministrador($roles) || count(array_intersect($permitidos, $roles)) > 0;
    }

    public function puedeAccion($accion, $estado, array $roles)
    {
        $definicion = $this->config['acciones'][$accion] ?? null;
        return $definicion && in_array((int) $estado, $definicion['estados'], true) && $this->tieneRol($definicion['roles'], $roles);
    }

    public function puedeTransicion($actual, $nuevo, array $roles)
    {
        $permitidos = $this->config['transiciones'][(int) $actual.'-'.(int) $nuevo] ?? null;
        return $permitidos !== null && $this->tieneRol($permitidos, $roles);
    }

    public function accionesDisponibles($estado, array $roles)
    {
        $acciones = [];
        foreach (array_keys($this->config['acciones']) as $accion) {
            if ($this->puedeAccion($accion, $estado, $roles)) {
                $acciones[] = $accion;
            }
        }
        if ((int) $estado !== 0 && $this->puedeTransicion($estado, 0, $roles)) {
            $acciones[] = 'rechazar';
        }
        return $acciones;
    }

    public function accionPrincipal($estado, array $roles)
    {
        foreach ($this->accionesDisponibles($estado, $roles) as $accion) {
            if (!in_array($accion, ['rechazar', 'editarCredito', 'reenviarCorreo'])) {
                return $accion;
            }
        }
        return null;
    }

    public function estadosVisibles(array $roles)
    {
        if ($this->esAdministrador($roles)) {
            return array_keys($this->config['estados']);
        }
        $estados = [];
        foreach ($roles as $rol) {
            $estados = array_merge($estados, $this->config['bandeja'][$rol] ?? []);
        }
        $estados = array_values(array_unique($estados));
        sort($estados);
        return $estados;
    }

    public function soloPropios(array $roles)
    {
        if ($this->esAdministrador($roles)) {
            return false;
        }
        return empty($roles) || count(array_diff($roles, $this->config['soloPropios'])) === 0;
    }

    public function validar($actual, $nuevo, array $roles, $idMotivo = null, array $condiciones = [])
    {
        $actual = (int) $actual;
        $nuevo = (int) $nuevo;
        $clave = $actual.'-'.$nuevo;
        if (!isset($this->config['transiciones'][$clave])) {
            return [422, 'No se permite pasar el proceso de "'.$this->nombreEstado($actual).'" a "'.$this->nombreEstado($nuevo).'".'];
        }
        if (!$this->tieneRol($this->config['transiciones'][$clave], $roles)) {
            return [403, 'Tu rol no permite pasar el proceso de "'.$this->nombreEstado($actual).'" a "'.$this->nombreEstado($nuevo).'".'];
        }
        if (in_array($nuevo, $this->config['motivoObligatorio'], true) && !$idMotivo) {
            return [422, 'Debes indicar el motivo del rechazo.'];
        }
        if ($clave === '2-3' && empty($condiciones['tratamientoCompleto'])) {
            return [422, 'El tratamiento de datos del proceso no está completo.'];
        }
        if ($clave === '2-3' && empty($condiciones['centralesVigente'])) {
            return [422, 'Consulta al menos una central de riesgo antes de aprobar.'];
        }
        if ($clave === '3-4' && !empty($condiciones['documentosPendientes'])) {
            return [422, 'Faltan documentos requeridos por aprobar: '.implode(', ', $condiciones['documentosPendientes']).'.'];
        }
        return null;
    }

    public function cambiar($idProceso, $nuevo, array $roles, $idMotivo = null, $observacion = null, $idUsuario = null)
    {
        $idUsuario = $idUsuario ?: auth()->id();
        return DB::transaction(function () use ($idProceso, $nuevo, $roles, $idMotivo, $observacion, $idUsuario) {
            $proceso = DB::selectOne('SELECT IdProceso, IdTercero, EstadoProceso, IdUsuario FROM Procesos WITH (UPDLOCK, ROWLOCK) WHERE IdProceso = ?', [$idProceso]);
            if (!$proceso) {
                return [422, 'El proceso no existe.'];
            }
            if (!Procesos::puedeVer($idProceso, $idUsuario, $roles, $this)) {
                return [403, 'No tienes acceso a este proceso.'];
            }
            $actual = (int) $proceso->EstadoProceso;
            $clave = $actual.'-'.(int) $nuevo;
            $condiciones = [
                'tratamientoCompleto' => $clave === '2-3' ? Procesos::estadoTratamiento($idProceso)['completo'] : null,
                'documentosPendientes' => $clave === '3-4' ? Procesos::documentosPendientes($idProceso) : [],
                'centralesVigente' => $clave === '2-3' ? CentralesRiesgo::instancia()->tieneVigente($proceso->IdTercero, date('Y-m-d H:i:s')) : null,
            ];
            if ($error = $this->validar($actual, $nuevo, $roles, $idMotivo, $condiciones)) {
                return $error;
            }
            $idMotivo = (int) $nuevo === 0 ? $idMotivo : null;
            if ($idMotivo && !DB::table('MotivosRechazo')->where('IdMotivo', $idMotivo)->where('EstadoMotivo', 1)->exists()) {
                return [422, 'El motivo de rechazo no existe.'];
            }
            DB::table('Procesos')->where('IdProceso', $idProceso)->update([
                'EstadoProceso' => (int) $nuevo,
                'updated_at' => date('Y-m-d\TH:i:s'),
            ]);
            self::registrarHistorial($idProceso, $actual, $nuevo, $idUsuario, $idMotivo, $observacion);
            return [200, null, $actual];
        });
    }

    public static function registrarHistorial($idProceso, $anterior, $nuevo, $idUsuario = null, $idMotivo = null, $observacion = null)
    {
        return DB::table('ProcesosHistorial')->insert([
            'IdProceso' => $idProceso,
            'EstadoAnterior' => $anterior,
            'EstadoNuevo' => $nuevo,
            'IdMotivo' => $idMotivo ?: null,
            'Observacion' => $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null,
            'IdUsuario' => $idUsuario ?: auth()->id(),
            'Fecha' => date('Y-m-d\TH:i:s'),
        ]);
    }
}
