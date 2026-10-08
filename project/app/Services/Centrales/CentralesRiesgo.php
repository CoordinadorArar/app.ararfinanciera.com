<?php

namespace App\Services\Centrales;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Throwable;

class CentralesRiesgo
{
    private $config;
    private $ajustes;

    public function __construct(array $config, array $ajustes = [])
    {
        $this->config = $config;
        $this->ajustes = $ajustes;
    }

    public static function instancia()
    {
        return new self(config('centrales'), DB::table('ConfiguracionCentrales')->pluck('Valor', 'Clave')->all());
    }

    public function claves()
    {
        return array_values(array_filter(array_keys($this->config['proveedores']), function ($clave) {
            return $clave !== 'simulado' || !empty($this->config['simulado_habilitado']);
        }));
    }

    public function existe($clave)
    {
        return in_array($clave, $this->claves(), true);
    }

    public function nombre($clave)
    {
        return $this->config['proveedores'][$clave]['nombre'] ?? $clave;
    }

    public function ambiente($clave)
    {
        return $this->config['proveedores'][$clave]['ambiente'] ?? null;
    }

    public function proveedor($clave): ProveedorCentral
    {
        $definicion = $this->config['proveedores'][$clave];
        return new $definicion['clase']($definicion);
    }

    public function habilitado($clave)
    {
        if ($clave === 'simulado') {
            return !empty($this->config['simulado_habilitado']);
        }
        return (string) ($this->ajustes['habilitado.'.$clave] ?? '1') === '1';
    }

    public function configurado($clave)
    {
        return $this->existe($clave) && $this->proveedor($clave)->configurado();
    }

    public function disponibles()
    {
        return array_values(array_filter($this->claves(), function ($clave) {
            return $this->habilitado($clave) && $this->configurado($clave);
        }));
    }

    public function predeterminado()
    {
        $valor = array_key_exists('predeterminado', $this->ajustes) ? $this->ajustes['predeterminado'] : $this->config['predeterminado'];
        return $valor === null || $valor === '' ? null : $valor;
    }

    public function predeterminadoDisponible()
    {
        $clave = $this->predeterminado();
        return $clave !== null && $this->existe($clave) && $this->habilitado($clave) && $this->configurado($clave);
    }

    public function vigenciaDias()
    {
        return max(1, (int) ($this->ajustes['vigenciaDias'] ?? $this->config['vigencia_dias']));
    }

    public function pendienteValidar($clave)
    {
        return $this->config['proveedores'][$clave]['pendiente_validar'] ?? null;
    }

    public static function vigenteHasta($fecha, $dias)
    {
        return date('Y-m-d H:i:s', strtotime($fecha.' +'.(int) $dias.' days'));
    }

    public static function reutilizable($ultima, $ahora, $forzar = false)
    {
        return !$forzar && $ultima && !empty($ultima['exitosa']) && !empty($ultima['vigenteHasta']) && strcmp($ultima['vigenteHasta'], $ahora) > 0;
    }

    public static function plan(array $proveedores, array $ultimas, $ahora, $forzar = false)
    {
        $plan = [];
        foreach ($proveedores as $clave) {
            $plan[$clave] = self::reutilizable($ultimas[$clave] ?? null, $ahora, $forzar) ? 'reutilizar' : 'consultar';
        }
        return $plan;
    }

    public function ejecutar($clave, array $tercero)
    {
        if (!$this->habilitado($clave)) {
            return ['ok' => false, 'codigo' => 'deshabilitado', 'error' => $this->nombre($clave).' está deshabilitado.'];
        }
        try {
            return ['ok' => true, 'resultado' => $this->proveedor($clave)->consultar($tercero)];
        } catch (CentralException $e) {
            return ['ok' => false, 'codigo' => $e->codigo, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            report($e);
            return ['ok' => false, 'codigo' => 'conexion', 'error' => 'Error inesperado al consultar '.$this->nombre($clave).'.'];
        }
    }

    private static function fila($fila)
    {
        return [
            'idConsulta' => (int) $fila->Id,
            'proveedor' => $fila->Proveedor,
            'ambiente' => $fila->Ambiente,
            'simulado' => (bool) $fila->Simulado,
            'exitosa' => (bool) $fila->Exitosa,
            'fechaConsulta' => $fila->FechaConsulta,
            'vigenteHasta' => $fila->VigenteHasta,
            'idUsuario' => $fila->IdUsuario ? (int) $fila->IdUsuario : null,
            'resumen' => $fila->ResumenJson ? json_decode($fila->ResumenJson, true) : null,
        ];
    }

    private static function consultaBase()
    {
        return DB::table('ConsultasCentrales')->selectRaw('Id,Proveedor,Ambiente,Simulado,Exitosa,CONVERT(varchar(19),FechaConsulta,120) AS FechaConsulta,CONVERT(varchar(19),VigenteHasta,120) AS VigenteHasta,IdUsuario,ResumenJson');
    }

    public function ultimas($idTercero, array $claves, $mismoAmbiente = true)
    {
        $ultimas = [];
        foreach ($claves as $clave) {
            $fila = self::consultaBase()->where('IdTercero', $idTercero)->where('Proveedor', $clave)->where('Exitosa', 1)
                ->when($mismoAmbiente, function ($consulta) use ($clave) {
                    $consulta->where('Ambiente', $this->ambiente($clave));
                })->orderByDesc('FechaConsulta')->orderByDesc('Id')->first();
            if ($fila) {
                $ultimas[$clave] = self::fila($fila);
            }
        }
        return $ultimas;
    }

    public function tieneVigente($idTercero, $ahora)
    {
        foreach ($this->ultimas($idTercero, $this->disponibles()) as $ultima) {
            if (self::reutilizable($ultima, $ahora)) {
                return true;
            }
        }
        return false;
    }

    public function ultimaGlobal($clave)
    {
        $fila = self::consultaBase()->where('Proveedor', $clave)->where('Exitosa', 1)->orderByDesc('FechaConsulta')->orderByDesc('Id')->first();
        return $fila ? self::fila($fila) : null;
    }

    public function guardar($idProceso, $idTercero, $clave, $idUsuario, $ahora, array $ejecucion)
    {
        $resultado = $ejecucion['resultado'] ?? null;
        $datos = [
            'IdProceso' => $idProceso,
            'IdTercero' => $idTercero,
            'Proveedor' => $clave,
            'Ambiente' => $this->ambiente($clave),
            'Simulado' => $resultado && $resultado->simulado ? 1 : 0,
            'FechaConsulta' => str_replace(' ', 'T', $ahora),
            'VigenteHasta' => $resultado ? str_replace(' ', 'T', self::vigenteHasta($ahora, $this->vigenciaDias())) : null,
            'IdUsuario' => $idUsuario,
            'Exitosa' => $resultado ? 1 : 0,
            'MensajeError' => $resultado ? null : mb_substr($ejecucion['error'], 0, 500),
            'ResumenJson' => $resultado ? json_encode($resultado->toArray(), JSON_UNESCAPED_UNICODE) : null,
            'RespuestaCruda' => $resultado ? $resultado->cruda : null,
        ];
        $id = DB::table('ConsultasCentrales')->insertGetId($datos, 'Id');
        return $resultado ? self::fila(self::consultaBase()->where('Id', $id)->first()) : null;
    }

    public function guardarAjustes(array $valores, $idUsuario)
    {
        DB::transaction(function () use ($valores, $idUsuario) {
            foreach ($valores as $clave => $valor) {
                $fila = DB::table('ConfiguracionCentrales')->where('Clave', $clave)->first();
                if ($fila && (string) $fila->Valor === (string) $valor) {
                    continue;
                }
                $datos = ['Valor' => (string) $valor, 'IdUsuario' => $idUsuario, 'updated_at' => date('Y-m-d\TH:i:s')];
                if ($fila) {
                    DB::table('ConfiguracionCentrales')->where('IdConfiguracion', $fila->IdConfiguracion)->update($datos);
                    $id = $fila->IdConfiguracion;
                } else {
                    $id = DB::table('ConfiguracionCentrales')->insertGetId(['Clave' => $clave] + $datos, 'IdConfiguracion');
                }
                Admin::auditar('ConfiguracionCentrales', $id, $clave, $fila->Valor ?? null, (string) $valor);
            }
        });
        $this->ajustes = array_merge($this->ajustes, array_map('strval', $valores));
    }
}
