<?php

namespace App\Services\Siesa;

use App\Services\ValidadorTercero;

class RegistroClienteSiesa
{
    const PASOS = [
        'tercero' => 'Tercero (0200)',
        'cliente' => 'Cliente (0201)',
        'proveedor' => 'Proveedor (0202)',
        'impuestos_cliente' => 'Impuestos y retenciones del cliente (0046/0047)',
        'impuestos_proveedor' => 'Impuestos del proveedor (0049)',
        'pago_bancolombia' => 'Pago electrónico Bancolombia (0634, formato 41)',
        'pago_bogota' => 'Pago electrónico Banco de Bogotá (0634, formato 7)',
    ];

    const DEPENDENCIAS = [
        'cliente' => 'tercero',
        'proveedor' => 'tercero',
        'impuestos_cliente' => 'cliente',
        'impuestos_proveedor' => 'proveedor',
        'pago_bancolombia' => 'proveedor',
        'pago_bogota' => 'proveedor',
    ];

    const PAGOS = ['pago_bancolombia' => '41', 'pago_bogota' => '7'];

    const TIPOS_IDENTIFICACION = [1 => ['C', '1', '1'], 2 => ['N', '2', '3'], 4 => ['E', '1', '2']];

    const TIPOS_CUENTA = [1 => '2', 2 => '1'];

    const PARTICULAS = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'SAN'];

    const TILDES = ['Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I', 'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U', 'Ñ' => 'N', 'Ç' => 'C'];

    const ENCABEZADO = '000000100000001007';

    public static function campo($valor, $largo, $izquierda = false)
    {
        $valor = mb_substr((string) $valor, 0, $largo);
        $relleno = str_repeat(' ', $largo - mb_strlen($valor));
        return $izquierda ? $relleno.$valor : $valor.$relleno;
    }

    public static function normalizar($texto)
    {
        $texto = strtr(mb_strtoupper((string) $texto), self::TILDES);
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\x20-\x7E]/u', ' ', $texto)));
    }

    public static function nitDv($idCliente, $dvFuente = null)
    {
        $id = strtoupper(trim((string) $idCliente));
        if (preg_match('/^(.+)-(\d)$/', $id, $m)) {
            return ['nit' => $m[1], 'dv' => $m[2]];
        }
        $dv = trim((string) $dvFuente);
        if ($dv === '' && ctype_digit($id)) {
            $dv = (string) ValidadorTercero::digitoVerificacion($id);
        }
        return ['nit' => $id, 'dv' => $dv === '' ? null : $dv];
    }

    public static function apellidos($apellidos)
    {
        $partes = preg_split('/\s+/', self::normalizar($apellidos), -1, PREG_SPLIT_NO_EMPTY);
        $primero = [];
        while ($partes && (!$primero || in_array(end($primero), self::PARTICULAS))) {
            $primero[] = array_shift($partes);
        }
        return [implode(' ', $primero), implode(' ', $partes)];
    }

    public static function datos(array $c, array $cuenta = null)
    {
        $nitDv = self::nitDv($c['IdCliente'] ?? '', $c['DigitoVerificaCli'] ?? null);
        $tipo = self::TIPOS_IDENTIFICACION[(int) ($c['TipoIdentificacionCliente'] ?? 0)] ?? [null, null, null];
        $juridica = $tipo[1] === '2';
        $nombres = self::normalizar($c['NomCliente'] ?? '');
        [$apellido1, $apellido2] = self::apellidos($c['ApeCliente'] ?? '');
        $razonSocial = implode(' ', array_filter([$nombres, $apellido1, $apellido2], 'strlen'));
        return [
            'idCliente' => trim((string) ($c['IdCliente'] ?? '')),
            'nit' => $nitDv['nit'],
            'dv' => $nitDv['dv'],
            'tipoIdentificacion' => $tipo[0],
            'tipoTercero' => $tipo[1],
            'tipoIdentificacionBancolombia' => $tipo[2],
            'razonSocial' => $razonSocial,
            'nombres' => $juridica ? '' : $nombres,
            'apellido1' => $juridica ? '' : $apellido1,
            'apellido2' => $juridica ? '' : $apellido2,
            'contacto' => $juridica ? $razonSocial : $nombres,
            'direccion' => self::normalizar($c['Direccion'] ?? ''),
            'pais' => '169',
            'departamento' => trim((string) ($c['IdDepartamento'] ?? '')),
            'ciudad' => trim((string) ($c['IdCiudad'] ?? '')),
            'telefono' => trim((string) ($c['Telefono'] ?? '')),
            'celular' => trim((string) ($c['Celular'] ?? '')),
            'email' => trim((string) ($c['Email'] ?? '')),
            'fechaNacimiento' => self::fecha($c['FechaNacimiento'] ?? null),
            'fechaIngreso' => self::fecha($c['FechaIngreso'] ?? null),
            'cuenta' => $cuenta ? [
                'banco' => trim((string) ($cuenta['Codigo'] ?? '')),
                'numero' => preg_replace('/\D/', '', (string) ($cuenta['NumCuenta'] ?? '')),
                'tipo' => self::TIPOS_CUENTA[(int) ($cuenta['TipoCuenta'] ?? 0)] ?? null,
            ] : null,
        ];
    }

    public static function motivos($clave, array $d)
    {
        $natural = $d['tipoTercero'] !== '2';
        $ubicacion = [
            'Falta el nombre o la razón social.' => $d['razonSocial'] === '',
            'Falta la dirección.' => $d['direccion'] === '',
            'Falta la ciudad (código DANE de departamento y ciudad).' => strlen($d['departamento']) !== 2 || strlen($d['ciudad']) !== 3,
        ];
        $reglas = ['Falta el número de identificación.' => $d['nit'] === ''];
        if ($clave === 'tercero') {
            $reglas += [
                'El tipo de identificación no está homologado con SIESA.' => $d['tipoIdentificacion'] === null,
                'El dígito de verificación no corresponde al NIT.' => !$natural && ($d['dv'] === null || !ctype_digit($d['nit']) || (string) ValidadorTercero::digitoVerificacion($d['nit']) !== $d['dv']),
                'Falta la fecha de nacimiento.' => $natural && $d['fechaNacimiento'] === null,
            ] + $ubicacion;
        } elseif (in_array($clave, ['cliente', 'proveedor'])) {
            $reglas += $ubicacion + ['Falta la fecha de ingreso (apertura del cliente en FactoringManager).' => $d['fechaIngreso'] === null];
        } elseif (isset(self::PAGOS[$clave])) {
            $reglas += [
                'El tipo de identificación no está homologado con SIESA.' => $d['tipoIdentificacion'] === null,
                'La cuenta bancaria no tiene banco.' => $d['cuenta']['banco'] === '',
                'La cuenta bancaria no tiene número.' => $d['cuenta']['numero'] === '',
                'El tipo de cuenta bancaria no está homologado con SIESA.' => $d['cuenta']['tipo'] === null,
            ];
        }
        return array_keys(array_filter($reglas));
    }

    public static function pasos(array $d, array $siesa)
    {
        $pasos = [];
        foreach (self::PASOS as $clave => $nombre) {
            $motivos = [];
            if (!empty($siesa[$clave])) {
                $estado = 'hecho';
            } elseif (isset(self::PAGOS[$clave]) && !$d['cuenta']) {
                $estado = 'omitido';
                $motivos[] = 'El cliente no tiene una cuenta bancaria registrada en FactoringManager; no se crea el pago electrónico.';
            } else {
                $motivos = self::motivos($clave, $d);
                $dependencia = self::DEPENDENCIAS[$clave] ?? null;
                if ($dependencia && empty($siesa[$dependencia])) {
                    $motivos[] = 'Requiere que '.self::PASOS[$dependencia].' exista en SIESA.';
                }
                $estado = $motivos ? 'bloqueado' : 'listo';
            }
            $pasos[] = ['clave' => $clave, 'nombre' => $nombre, 'estado' => $estado, 'motivos' => $motivos];
        }
        return $pasos;
    }

    public static function lineas($clave, array $d)
    {
        $nit = [$d['nit'], 15];
        switch ($clave) {
            case 'tercero':
                return [self::prefijo('0200', '08', '1').self::unir([
                    $nit, [$d['nit'], 25], [$d['tipoTercero'] === '2' ? $d['dv'] : '0', 3], [$d['tipoIdentificacion'], 1], [$d['tipoTercero'], 1],
                    [$d['razonSocial'], 100], [$d['apellido1'], 29], [$d['apellido2'], 29], [$d['nombres'], 40], ['', 50], '100000',
                    [$d['contacto'], 50], [$d['direccion'], 40], ['', 40], ['', 40], [$d['pais'], 3], [$d['departamento'], 2], [$d['ciudad'], 3],
                    ['', 40], [$d['telefono'], 20], ['', 20], ['', 10], [$d['email'], 255], [$d['fechaNacimiento'], 8], ['', 4], '01', [$d['celular'], 50],
                ])];
            case 'cliente':
                return [self::prefijo('0201', '09', '0').self::unir([
                    $nit, '001', '1', [$d['razonSocial'], 40], 'COP', 'VEN1', 'A', 'C00', '000', ['000000000000000.0000', 21], ['', 15], ['', 3],
                    ['001', 4], ['', 4], '001', '1', '0000.00', ['0', 7, true], ['100', 7, true], '1000', ['', 3], ['', 255],
                    [$d['contacto'], 50], [$d['direccion'], 40], ['', 40], ['', 40], [$d['pais'], 3], [$d['departamento'], 2], [$d['ciudad'], 3],
                    ['', 40], [$d['telefono'], 20], ['', 20], ['', 10], [$d['email'], 255], [$d['fechaIngreso'], 8], ['', 3], ['', 20], ['', 4],
                    ['', 35], ['', 8], '0000.00', '00', ['', 3], 'VEN1', '0', '0',
                ])];
            case 'proveedor':
                return [self::prefijo('0202', '03', '0').self::unir([
                    $nit, '001', '1', [$d['razonSocial'], 40], 'COP', 'PVAC', 'C30', ['0', 3], '+000000000000000.0000', ['015', 4], '1', ['', 255],
                    [$d['razonSocial'], 50], [$d['direccion'], 40], ['', 40], ['', 40], [$d['pais'], 3], [$d['departamento'], 2], [$d['ciudad'], 3],
                    ['', 40], [$d['celular'], 20], ['', 20], ['', 10], [$d['email'], 50], [$d['fechaIngreso'], 8], '000.00', '0000000000000.00', '0', '0', '0',
                ])];
            case 'impuestos_cliente':
                return [
                    self::prefijo('0046', '01', '1').self::unir([$nit]).'0011  1 IV19',
                    self::prefijo('0047', '01', '1', 3).self::unir([$nit]).'00180 1 9001',
                ];
            case 'impuestos_proveedor':
                return [self::prefijo('0049', '01', '1').self::unir([$nit]).'0011  1 IV19'];
            case 'pago_bancolombia':
            case 'pago_bogota':
                $cuenta = $d['cuenta'] ?: ['banco' => '', 'numero' => '', 'tipo' => ''];
                $datos = $clave === 'pago_bancolombia'
                    ? [$d['nit'], $d['tipoIdentificacionBancolombia'], '000000000', $cuenta['numero'], '6', '10', '', '302']
                    : [$d['tipoIdentificacion'], $d['nit'], '1', $cuenta['numero'], '001', '1', 'N', '', '7000'];
                return [self::prefijo('0634', '01', '0').self::unir(array_merge(
                    [$nit, '001', '1', [$cuenta['banco'], 10], [$cuenta['numero'], 30], [$cuenta['tipo'], 1], [self::PAGOS[$clave], 8], '1', '1'],
                    array_map(function ($valor) {
                        return [$valor, 50];
                    }, array_pad($datos, 15, ''))
                ))];
        }
        return [];
    }

    public static function xml(array $lineas, array $conexion, $enmascarar = false)
    {
        $datos = array_merge([self::ENCABEZADO], $lineas, [str_pad(count($lineas) + 2, 7, '0', STR_PAD_LEFT).'99990001007']);
        $xml = "<Importar>\r\n<NombreConexion>".self::escapar($conexion['conexion'] ?? '')."</NombreConexion>\r\n<IdCia>".self::escapar($conexion['id_cia'] ?? '')
            ."</IdCia>\r\n<Usuario>".self::escapar($conexion['usuario'] ?? '')."</Usuario>\r\n<Clave>".($enmascarar ? '********' : self::escapar($conexion['clave'] ?? ''))
            ."</Clave>\r\n<Datos>\r\n";
        foreach ($datos as $linea) {
            $xml .= '<Linea>'.self::escapar($linea)."</Linea>\r\n";
        }
        return $xml."</Datos>\r\n</Importar>";
    }

    private static function prefijo($tipo, $version, $actualiza, $numero = 2)
    {
        return str_pad($numero, 7, '0', STR_PAD_LEFT).$tipo.'00'.$version.'007'.$actualiza;
    }

    private static function unir(array $campos)
    {
        return implode('', array_map(function ($campo) {
            return is_array($campo) ? self::campo($campo[0], $campo[1], $campo[2] ?? false) : $campo;
        }, $campos));
    }

    private static function escapar($valor)
    {
        return htmlspecialchars((string) $valor, ENT_XML1, 'UTF-8');
    }

    private static function fecha($valor)
    {
        $tiempo = $valor ? strtotime((string) $valor) : false;
        return $tiempo ? date('Ymd', $tiempo) : null;
    }
}
