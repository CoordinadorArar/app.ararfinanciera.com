<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;

class ValidadorTercero
{
    const DOCUMENTO_MAXIMO = 2147483647;

    public static function tipoDocumento($nombre)
    {
        $nombre = mb_strtolower(trim((string) $nombre));
        $sigla = preg_replace('/[^a-z]/', '', $nombre);
        if (strpos($nombre, 'nit') !== false || strpos($nombre, 'tributari') !== false) {
            return 'NIT';
        }
        if (strpos($nombre, 'extranjer') !== false || $sigla === 'ce') {
            return 'CE';
        }
        if (strpos($nombre, 'pasaporte') !== false || in_array($sigla, ['pa', 'pp'])) {
            return 'PA';
        }
        if (strpos($nombre, 'ciudadan') !== false || $sigla === 'cc') {
            return 'CC';
        }
        return null;
    }

    public static function documento($tipo, $valor)
    {
        $valor = trim((string) $valor);
        $reglas = [
            'CC' => ['/^\d{5,10}$/', 'La cédula de ciudadanía debe tener entre 5 y 10 dígitos numéricos.'],
            'CE' => ['/^\d{3,10}$/', 'La cédula de extranjería debe tener entre 3 y 10 dígitos numéricos.'],
            'NIT' => ['/^\d{6,10}(-\d)?$/', 'El NIT debe tener entre 6 y 10 dígitos y, opcionalmente, el dígito de verificación separado por guion (900123456-7).'],
            'PA' => ['/^\d{5,10}$/', 'El pasaporte debe tener entre 5 y 10 dígitos.'],
        ];
        list($patron, $mensaje) = $reglas[$tipo] ?? ['/^[A-Za-z0-9]{3,15}$/', 'El documento debe tener entre 3 y 15 caracteres alfanuméricos.'];
        if (!preg_match($patron, $valor)) {
            return $mensaje;
        }
        if ($tipo === 'NIT' && strpos($valor, '-') !== false) {
            list($nit, $dv) = explode('-', $valor);
            if (self::digitoVerificacion($nit) !== (int) $dv) {
                return 'El dígito de verificación del NIT no es válido.';
            }
        }
        return null;
    }

    public static function baseDocumento($valor)
    {
        return explode('-', trim((string) $valor))[0];
    }

    public static function documentoAlmacenable($valor)
    {
        $base = self::baseDocumento($valor);
        return ctype_digit($base) && (float) $base <= self::DOCUMENTO_MAXIMO;
    }

    public static function digitoVerificacion($nit)
    {
        $pesos = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
        $suma = 0;
        foreach (array_reverse(str_split((string) $nit)) as $i => $digito) {
            $suma += (int) $digito * $pesos[$i];
        }
        $residuo = $suma % 11;
        return $residuo > 1 ? 11 - $residuo : $residuo;
    }

    public static function fechaNacimiento($fecha, $hoy = null)
    {
        $nacimiento = self::fecha($fecha);
        if (!$nacimiento) {
            return 'La fecha de nacimiento no es válida.';
        }
        $hoy = $hoy ? Carbon::parse($hoy)->startOfDay() : Carbon::today();
        if ($nacimiento->greaterThan($hoy)) {
            return 'La fecha de nacimiento no puede ser futura.';
        }
        $edad = $nacimiento->diffInYears($hoy);
        if ($edad < 18) {
            return 'El titular debe ser mayor de 18 años.';
        }
        if ($edad >= 100) {
            return 'El titular debe ser menor de 100 años.';
        }
        return null;
    }

    public static function fechaExpedicion($fecha, $fechaNacimiento, $hoy = null)
    {
        $expedicion = self::fecha($fecha);
        if (!$expedicion) {
            return 'La fecha de expedición no es válida.';
        }
        $hoy = $hoy ? Carbon::parse($hoy)->startOfDay() : Carbon::today();
        if ($expedicion->greaterThan($hoy)) {
            return 'La fecha de expedición no puede ser futura.';
        }
        $nacimiento = self::fecha($fechaNacimiento);
        if ($nacimiento && $expedicion->lessThan($nacimiento->copy()->addYears(18))) {
            return 'La fecha de expedición debe ser posterior a la fecha en que el titular cumplió 18 años.';
        }
        return null;
    }

    public static function limpiarTelefono($valor)
    {
        return preg_replace('/[\s\-\.\(\)]/', '', trim((string) $valor));
    }

    public static function telefono($valor)
    {
        $valor = self::limpiarTelefono($valor);
        if (preg_match('/^3\d{9}$/', $valor) || preg_match('/^(?:[1-9]\d{6}|[124-9]\d{7,9})$/', $valor)) {
            return null;
        }
        return 'Ingresa un celular de 10 dígitos que empiece por 3 o un teléfono fijo de 7 a 10 dígitos.';
    }

    private static function fecha($valor)
    {
        $valor = trim((string) $valor);
        if (!preg_match('/^\d{4}[-\/]\d{2}[-\/]\d{2}$/', $valor)) {
            return null;
        }
        try {
            $fecha = Carbon::createFromFormat('!Y-m-d', str_replace('/', '-', $valor));
        } catch (Exception $e) {
            return null;
        }
        return $fecha->format('Y-m-d') === str_replace('/', '-', $valor) ? $fecha : null;
    }
}
