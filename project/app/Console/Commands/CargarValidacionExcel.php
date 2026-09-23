<?php

namespace App\Console\Commands;

use App\Models\Deterioro;
use App\Support\Ambiente;
use Illuminate\Console\Command;

/**
 * Carga las cifras de una hoja del libro para un corte, desde un CSV de dos
 * columnas: columna,valor_excel.
 *
 * Es lo que alimenta la conciliación de la pantalla de evolución. Va por
 * archivo y no por seeder porque el libro se rehace cada mes; el upsert del
 * modelo hace que repetir la carga deje la tabla igual.
 */
class CargarValidacionExcel extends Command
{
    protected $signature = 'deterioro:cargar-validacion-excel {idCorte} {archivo} {--hoja=DETERIORO} {--ambiente=produccion}';

    protected $description = 'Carga las cifras del libro de un corte en det_validacion_excel desde un CSV';

    public function handle()
    {
        $ambiente = Ambiente::aplicar($this->option('ambiente'));
        $this->info('Ambiente: '.$ambiente.' · base '.config('database.connections.sqlsrv.database'));

        $archivo = $this->argument('archivo');
        if (!is_file($archivo) || !is_readable($archivo)) {
            $this->error("No se puede leer el archivo: $archivo");
            return 1;
        }

        $idCorte = (int) $this->argument('idCorte');
        if (!Deterioro::corte($idCorte)) {
            $this->error("El corte $idCorte no existe.");
            return 1;
        }

        $puntero = fopen($archivo, 'r');
        $cabecera = fgetcsv($puntero);
        if ($cabecera) {
            $cabecera[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecera[0]);
            $cabecera = array_map(function ($c) {
                return strtolower(trim($c));
            }, $cabecera);
        }
        if ($cabecera !== ['columna', 'valor_excel']) {
            fclose($puntero);
            $this->error('La cabecera no corresponde. Se esperaba: columna,valor_excel');
            return 1;
        }

        $valores = [];
        $linea = 1;
        while (($fila = fgetcsv($puntero)) !== false) {
            $linea++;
            if (count($fila) === 1 && trim((string) $fila[0]) === '') {
                continue;
            }
            if (count($fila) !== 2 || trim($fila[0]) === '' || !is_numeric(trim($fila[1]))) {
                fclose($puntero);
                $this->error("Archivo rechazado: línea $linea inválida y no se escribió ninguna.");
                return 1;
            }
            $valores[trim($fila[0])] = (float) trim($fila[1]);
        }
        fclose($puntero);

        $hoja = $this->option('hoja');
        $cargadas = Deterioro::registrarValidacionExcel($idCorte, $hoja, $valores);
        $this->info("Corte $idCorte · hoja $hoja · $cargadas cifras cargadas.");

        Deterioro::registrarBitacora('CARGAR_VALIDACION_EXCEL', $idCorte, null, null,
            "$archivo · hoja $hoja, $cargadas cifras", 0);

        return 0;
    }
}
