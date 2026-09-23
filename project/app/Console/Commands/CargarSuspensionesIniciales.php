<?php

namespace App\Console\Commands;

use App\Models\Deterioro;
use App\Support\Ambiente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cargue inicial de suspensiones de intereses (D-14), desde el CSV que
 * reproduce el archivo de Contabilidad: cedula,nombre,operacion,causal,
 * fecha_evento,observacion,soporte.
 *
 * Contabilidad no pudo determinar la causal ni la fecha del evento de estas
 * 281 operaciones: la causal que no viene en el archivo se marca
 * SIN_DETERMINAR en vez de inventar una de las tres reales (D-05), y la
 * fecha que no viene se deriva del último documento FAT del cliente en
 * SIESA. El mismo comando sirve cuando Contabilidad complete el archivo,
 * porque respeta lo que ya venga en cada fila.
 */
class CargarSuspensionesIniciales extends Command
{
    protected $signature = 'deterioro:cargar-suspensiones {archivo} {--ambiente=produccion} {--simular}';

    protected $description = 'Cargue inicial de suspensiones de intereses (D-14) desde el archivo de Contabilidad';

    const CAUSAL_SIN_DETERMINAR = 'SIN_DETERMINAR';
    const CABECERA = ['cedula', 'nombre', 'operacion', 'causal', 'fecha_evento', 'observacion', 'soporte'];

    public function handle()
    {
        $ambiente = Ambiente::aplicar($this->option('ambiente'));
        $this->info('Ambiente: '.$ambiente.' · base '.config('database.connections.sqlsrv.database'));

        $archivo = $this->argument('archivo');
        if (!is_file($archivo) || !is_readable($archivo)) {
            $this->error("No se puede leer el archivo: $archivo");
            return 1;
        }

        $causalesVigentes = collect(Deterioro::causalesSuspension())
            ->where('activa', 1)->pluck('codigo')->all();

        $filas = $this->leerYValidar($archivo, $causalesVigentes);
        if ($filas === null) {
            return 1;
        }
        if (!$filas) {
            $this->error('El archivo no tiene filas.');
            return 1;
        }

        // Paso 2 · resolución de la fecha del evento por precedencia.
        $deArchivo = 0;
        $deSiesa = 0;
        $sinFecha = [];
        foreach ($filas as &$f) {
            if ($f['fecha_evento'] !== null) {
                $deArchivo++;
                continue;
            }
            $fecha = Deterioro::fechaUltimaFacturaSiesa($f['cedula']);
            if ($fecha) {
                $f['fecha_evento'] = $fecha;
                $deSiesa++;
            } else {
                $sinFecha[] = $f;
            }
        }
        unset($f);

        $cargables = array_values(array_filter($filas, function ($f) {
            return $f['fecha_evento'] !== null;
        }));

        // Corte más reciente, para existe_en_factoring.
        $ultimoCorte = DB::table('det_corte')->orderByDesc('fecha_corte')->first();
        $operacionesCorte = $ultimoCorte
            ? DB::table('det_deterioro_operacion')->where('id_corte', $ultimoCorte->id_corte)
                ->pluck('id_operacion')->flip()
            : collect();

        $activasExistentes = DB::table('det_suspension_interes')
            ->whereNull('fecha_reactivacion')->pluck('id_operacion')->flip();

        $creadas = 0;
        $actualizadas = 0;
        $congeladas = 0;
        $sinCongelar = 0;
        $facturasFat = 0;
        $montoCongelado = 0;
        $enFactoring = 0;
        $fueraFactoring = 0;

        // El interés congelado de todas las marcas se resuelve en una sola
        // consulta. Una por fila serían otros tantos recorridos completos de las
        // FAT de la compañía contra la base del ERP; agrupadas es uno.
        $congelados = Deterioro::interesALaFechaDelEventoEnLote(array_map(function ($f) {
            return ['id_operacion' => $f['operacion'], 'fecha_evento' => $f['fecha_evento']];
        }, $cargables));

        foreach ($cargables as $f) {
            $existeEnFactoring = $operacionesCorte->has($f['operacion']);
            $existeEnFactoring ? $enFactoring++ : $fueraFactoring++;

            $congelado = $congelados[Deterioro::claveMarcaCongelada($f['operacion'], $f['fecha_evento'])];
            if ($congelado['interes_congelado'] !== null) {
                $congeladas++;
                $facturasFat += $congelado['facturas_fat'];
                $montoCongelado += $congelado['interes_congelado'];
            } else {
                $sinCongelar++;
            }

            $activasExistentes->has($f['operacion']) ? $actualizadas++ : $creadas++;

            if (!$this->option('simular')) {
                Deterioro::registrarSuspensionCargueInicial([
                    'id_operacion' => $f['operacion'],
                    'causal' => $f['causal'],
                    'fecha_evento' => $f['fecha_evento'],
                    'observacion' => $f['observacion'],
                    'soporte' => $f['soporte'],
                    'existe_en_factoring' => $existeEnFactoring,
                    'interes_congelado' => $congelado['interes_congelado'],
                    'id_corte_congelado' => $congelado['id_corte_congelado'],
                ]);
            }
        }

        $this->imprimirResumen(count($filas), $creadas, $actualizadas, $deArchivo, $deSiesa,
            $sinFecha, $congeladas, $sinCongelar, $facturasFat, $montoCongelado, $enFactoring, $fueraFactoring);

        if ($this->option('simular')) {
            $this->info('--simular: no se escribió nada.');
            return 0;
        }

        Deterioro::registrarBitacora('CARGUE_INICIAL_SUSPENSIONES', null, null, null,
            "$archivo · ".count($filas)." filas, $creadas creadas, $actualizadas actualizadas, "
            .count($sinFecha).' sin fecha resoluble', 0);

        return 0;
    }

    /**
     * Valida el archivo completo y devuelve las filas parseadas, o null si
     * algo falla (ya se imprimió el error con la línea).
     */
    private function leerYValidar($archivo, $causalesVigentes)
    {
        $puntero = fopen($archivo, 'r');
        $cabecera = fgetcsv($puntero);
        if ($cabecera) {
            $cabecera[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecera[0]);
            $cabecera = array_map(function ($c) {
                return strtolower(trim($c));
            }, $cabecera);
        }
        if ($cabecera !== self::CABECERA) {
            fclose($puntero);
            $this->error('La cabecera no corresponde. Se esperaba: '.implode(',', self::CABECERA));
            return null;
        }

        $filas = [];
        $operaciones = [];
        $linea = 1;
        while (($fila = fgetcsv($puntero)) !== false) {
            $linea++;
            if (count($fila) === 1 && trim((string) $fila[0]) === '') {
                continue;
            }
            if (count($fila) !== 7) {
                fclose($puntero);
                $this->error("Archivo rechazado: línea $linea con número de columnas inválido y no se escribió ninguna.");
                return null;
            }

            [$cedula, $nombre, $operacion, $causal, $fechaEvento, $observacion, $soporte] = array_map('trim', $fila);

            if ($cedula === '') {
                fclose($puntero);
                $this->error("Archivo rechazado: línea $linea sin cédula y no se escribió ninguna.");
                return null;
            }
            if ($operacion === '' || !ctype_digit($operacion)) {
                fclose($puntero);
                $this->error("Archivo rechazado: línea $linea con operación inválida y no se escribió ninguna.");
                return null;
            }
            $operacion = (int) $operacion;
            if (isset($operaciones[$operacion])) {
                fclose($puntero);
                $this->error("Archivo rechazado: línea $linea repite la operación $operacion (ya en la línea {$operaciones[$operacion]}) y no se escribió ninguna.");
                return null;
            }
            $operaciones[$operacion] = $linea;

            if ($fechaEvento !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEvento) || !strtotime($fechaEvento))) {
                fclose($puntero);
                $this->error("Archivo rechazado: línea $linea con fecha_evento inválida (se espera AAAA-MM-DD) y no se escribió ninguna.");
                return null;
            }
            if (strlen($observacion) > 500) {
                fclose($puntero);
                $this->error("Archivo rechazado: línea $linea con observación mayor a 500 caracteres y no se escribió ninguna.");
                return null;
            }
            if (strlen($soporte) > 255) {
                fclose($puntero);
                $this->error("Archivo rechazado: línea $linea con soporte mayor a 255 caracteres y no se escribió ninguna.");
                return null;
            }
            if ($causal !== '' && !in_array($causal, $causalesVigentes)) {
                fclose($puntero);
                $this->error("Archivo rechazado: línea $linea con causal '$causal' inexistente o no vigente y no se escribió ninguna.");
                return null;
            }

            $filas[] = [
                'linea' => $linea,
                'cedula' => $cedula,
                'operacion' => $operacion,
                'causal' => $causal !== '' ? $causal : self::CAUSAL_SIN_DETERMINAR,
                'fecha_evento' => $fechaEvento !== '' ? $fechaEvento : null,
                'observacion' => $observacion !== '' ? $observacion
                    : 'Cargue inicial de suspensiones (D-14) desde '.basename($archivo),
                'soporte' => $soporte !== '' ? $soporte : null,
            ];
        }
        fclose($puntero);

        return $filas;
    }

    private function imprimirResumen($leidas, $creadas, $actualizadas, $deArchivo, $deSiesa, $sinFecha,
        $congeladas, $sinCongelar, $facturasFat, $montoCongelado, $enFactoring, $fueraFactoring)
    {
        $this->info("Filas leídas: $leidas");
        $this->info("Marcas creadas: $creadas · actualizadas: $actualizadas");
        $this->info("Fecha del evento tomada del archivo: $deArchivo · tomada de SIESA: $deSiesa");

        $this->info('Operaciones sin fecha resoluble (excluidas del cargue): '.count($sinFecha));
        foreach ($sinFecha as $f) {
            $this->line("  - cédula {$f['cedula']}, operación {$f['operacion']}");
        }

        $this->info("Marcas con interés congelado: $congeladas · sin congelar: $sinCongelar");
        if ($congeladas) {
            // El anclaje ya no es un corte del módulo sino las FAT de SIESA, así
            // que lo que se reporta es de cuántas facturas salió y por cuánto.
            $this->line('  - origen: '.$facturasFat.' factura(s) FAT hasta el mes del evento');
            $this->line('  - interés congelado total: '.number_format($montoCongelado, 2, ',', '.'));
        }
        if ($sinCongelar) {
            $this->line("  - $sinCongelar sin ninguna FAT hasta el mes del evento: quedan sin congelar y las señala C-MARCAS");
        }

        $this->info("Operaciones cargadas que existen en el corte más reciente: $enFactoring · que no existen: $fueraFactoring");
    }
}
