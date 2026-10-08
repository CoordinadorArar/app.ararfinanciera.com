<?php

namespace App\Services;

use InvalidArgumentException;

class EvaluadorFormula
{
    const PRECEDENCIA = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

    public static function evaluar($configuracion, array $valores)
    {
        $mapa = [];
        foreach ($valores as $nombre => $valor) {
            $mapa[trim((string) $nombre)] = $valor;
        }
        $tokens = self::tokenizar($configuracion, $mapa);
        $operacion = implode('', array_map(function ($token) {
            return $token['texto'];
        }, $tokens));
        $resultado = self::calcular(self::aPostfijo($tokens));
        if ($resultado == floor($resultado) && abs($resultado) < PHP_INT_MAX) {
            $resultado = (int) $resultado;
        }
        return ['resultado' => $resultado, 'operacion' => $operacion];
    }

    public static function validar($configuracion, array $nombres, $ignorarMayusculas = false)
    {
        $mapa = [];
        foreach ($nombres as $nombre) {
            $mapa[$ignorarMayusculas ? mb_strtolower(trim((string) $nombre)) : trim((string) $nombre)] = 1;
        }
        if ($ignorarMayusculas) {
            $configuracion = mb_strtolower((string) $configuracion);
        }
        self::aPostfijo(self::tokenizar($configuracion, $mapa));
        return true;
    }

    public static function valoresOperacion($configuracion, $operacion)
    {
        $valores = [];
        $resto = (string) $operacion;
        foreach (explode('|', (string) $configuracion) as $parte) {
            $parte = trim($parte);
            if ($parte === '') {
                continue;
            }
            if ($parte !== 'x' && $parte !== 'X' && preg_match('/^[\p{L}_][\p{L}\p{N}_]*$/u', $parte)) {
                if (!preg_match('/^-?\d+(\.\d+)?/', $resto, $coincidencia)) {
                    return [];
                }
                $valores[$parte] = $coincidencia[0];
                $resto = substr($resto, strlen($coincidencia[0]));
                continue;
            }
            $texto = ($parte === 'x' || $parte === 'X') ? '*' : $parte;
            if (strpos($resto, $texto) !== 0) {
                return [];
            }
            $resto = substr($resto, strlen($texto));
        }
        return $resto === '' ? $valores : [];
    }

    public static function limpiarValor($valor)
    {
        $limpio = str_replace(['$', ',', ' '], '', trim((string) $valor));
        if ($limpio === '' || !preg_match('/^-?\d+(\.\d+)?$/', $limpio)) {
            return null;
        }
        return $limpio;
    }

    private static function tokenizar($configuracion, array $mapa)
    {
        $tokens = [];
        foreach (explode('|', (string) $configuracion) as $parte) {
            $parte = trim($parte);
            if ($parte === '') {
                continue;
            }
            if ($parte === 'x' || $parte === 'X') {
                $parte = '*';
            }
            if (isset(self::PRECEDENCIA[$parte])) {
                $tokens[] = ['tipo' => 'operador', 'valor' => $parte, 'texto' => $parte];
            } elseif ($parte === '(' || $parte === ')') {
                $tokens[] = ['tipo' => $parte, 'valor' => $parte, 'texto' => $parte];
            } elseif (preg_match('/^\d+(\.\d+)?$/', $parte)) {
                $ultimo = count($tokens) - 1;
                if ($ultimo >= 0 && $tokens[$ultimo]['tipo'] === 'literal') {
                    $texto = $tokens[$ultimo]['texto'].$parte;
                    if (!preg_match('/^\d+(\.\d+)?$/', $texto)) {
                        throw new InvalidArgumentException('La fórmula contiene un número inválido: '.$texto);
                    }
                    $tokens[$ultimo] = ['tipo' => 'literal', 'valor' => (float) $texto, 'texto' => $texto];
                } else {
                    $tokens[] = ['tipo' => 'literal', 'valor' => (float) $parte, 'texto' => $parte];
                }
            } elseif (preg_match('/^[\p{L}_][\p{L}\p{N}_]*$/u', $parte) && array_key_exists($parte, $mapa)) {
                $valor = self::limpiarValor($mapa[$parte]);
                if ($valor === null) {
                    throw new InvalidArgumentException('El valor de "'.$parte.'" no es numérico.');
                }
                $tokens[] = ['tipo' => 'numero', 'valor' => (float) $valor, 'texto' => $valor];
            } else {
                throw new InvalidArgumentException('La fórmula contiene un elemento no permitido: '.$parte);
            }
        }
        if (count($tokens) === 0) {
            throw new InvalidArgumentException('La fórmula está vacía.');
        }
        return $tokens;
    }

    private static function aPostfijo(array $tokens)
    {
        $salida = [];
        $pila = [];
        $esperaOperando = true;
        foreach ($tokens as $token) {
            switch ($token['tipo']) {
                case 'numero':
                case 'literal':
                    if (!$esperaOperando) {
                        throw new InvalidArgumentException('Falta un operador antes de "'.$token['texto'].'".');
                    }
                    $salida[] = $token['valor'];
                    $esperaOperando = false;
                    break;
                case '(':
                    if (!$esperaOperando) {
                        throw new InvalidArgumentException('Falta un operador antes de "(".');
                    }
                    $pila[] = '(';
                    break;
                case ')':
                    if ($esperaOperando) {
                        throw new InvalidArgumentException('La fórmula tiene un paréntesis vacío o mal ubicado.');
                    }
                    while (count($pila) > 0 && end($pila) !== '(') {
                        $salida[] = array_pop($pila);
                    }
                    if (count($pila) === 0) {
                        throw new InvalidArgumentException('La fórmula tiene paréntesis desbalanceados.');
                    }
                    array_pop($pila);
                    break;
                default:
                    if ($esperaOperando) {
                        throw new InvalidArgumentException('El operador "'.$token['valor'].'" está mal ubicado.');
                    }
                    while (count($pila) > 0 && end($pila) !== '(' && self::PRECEDENCIA[end($pila)] >= self::PRECEDENCIA[$token['valor']]) {
                        $salida[] = array_pop($pila);
                    }
                    $pila[] = $token['valor'];
                    $esperaOperando = true;
            }
        }
        if ($esperaOperando) {
            throw new InvalidArgumentException('La fórmula termina en un operador.');
        }
        while (count($pila) > 0) {
            $operador = array_pop($pila);
            if ($operador === '(') {
                throw new InvalidArgumentException('La fórmula tiene paréntesis desbalanceados.');
            }
            $salida[] = $operador;
        }
        return $salida;
    }

    private static function calcular(array $postfijo)
    {
        $pila = [];
        foreach ($postfijo as $elemento) {
            if (!is_string($elemento)) {
                $pila[] = $elemento;
                continue;
            }
            $b = array_pop($pila);
            $a = array_pop($pila);
            switch ($elemento) {
                case '+': $pila[] = $a + $b; break;
                case '-': $pila[] = $a - $b; break;
                case '*': $pila[] = $a * $b; break;
                case '/':
                    if ($b == 0) {
                        throw new InvalidArgumentException('La fórmula produce una división por cero.');
                    }
                    $pila[] = $a / $b;
                    break;
            }
        }
        return $pila[0];
    }
}
