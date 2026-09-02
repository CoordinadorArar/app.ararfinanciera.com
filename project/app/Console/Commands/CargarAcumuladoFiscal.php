<?php

namespace App\Console\Commands;

use App\Models\Deterioro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga el acumulado fiscal por operación y año gravable desde un CSV.
 *
 * Es la migración de la hoja `1399 AÑO 2025` del libro, que hoy mantiene el
 * acumulado sumando términos dentro de la celda. Alimenta P en RN-09, así que
 * un error aquí se arrastra por años: el archivo se rechaza entero, sin
 * escribir nada, si la cabecera no coincide o si alguna fila es inválida.
 *
 * El upsert por (id_operacion, año) hace la carga repetible: correrla dos veces
 * con el mismo archivo deja la tabla igual.
 */
class CargarAcumuladoFiscal extends Command
{
    protected $signature = 'deterioro:cargar-acumulado-fiscal {archivo} {--origen=EXCEL_1399}';

    protected $description = 'Carga el acumulado fiscal por operación y año gravable desde un CSV';

    const CABECERA = ['id_operacion', 'ano_gravable', 'valor_deducido'];

    public function handle()
    {
        $archivo = $this->argument('archivo');
        if (!is_file($archivo) || !is_readable($archivo)) {
            $this->error("No se puede leer el archivo: $archivo");
            return 1;
        }

        $puntero = fopen($archivo, 'r');
        $cabecera = fgetcsv($puntero);
        if ($cabecera) {
            // El primer campo puede traer BOM si el CSV salió de Excel.
            $cabecera[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecera[0]);
            $cabecera = array_map(function ($c) {
                return strtolower(trim($c));
            }, $cabecera);
        }
        if ($cabecera !== self::CABECERA) {
            fclose($puntero);
            $this->error('La cabecera no corresponde. Se esperaba: ' . implode(',', self::CABECERA));
            return 1;
        }

        $origen = $this->option('origen');
        $ahora = Deterioro::ahora();
        $validas = [];
        $rechazos = [];
        $linea = 1;

        while (($fila = fgetcsv($puntero)) !== false) {
            $linea++;
            if ($fila === [null] || (count($fila) === 1 && trim((string) $fila[0]) === '')) {
                continue;
            }

            $error = $this->validar($fila);
            if ($error) {
                $rechazos[] = "línea $linea: $error";
                continue;
            }

            $validas[] = [
                'id_operacion' => (int) trim($fila[0]),
                'ano_gravable' => (int) trim($fila[1]),
                'valor_deducido' => (float) trim($fila[2]),
            ];
        }
        fclose($puntero);

        $leidas = count($validas) + count($rechazos);

        // Todo o nada: una carga parcial alimentaría P en RN-09 con un
        // acumulado incompleto y nada volvería a señalarlo.
        if ($rechazos) {
            $this->error('Archivo rechazado: ' . count($rechazos) . ' de ' . $leidas
                . ' filas son inválidas y no se escribió ninguna.');
            foreach ($rechazos as $rechazo) {
                $this->warn($rechazo);
            }
            return 1;
        }

        $insertadas = $actualizadas = 0;

        try {
            DB::transaction(function () use ($validas, $origen, $ahora, &$insertadas, &$actualizadas) {
                foreach ($validas as $fila) {
                    $existente = DB::table('det_fiscal_acumulado')
                        ->where('id_operacion', $fila['id_operacion'])
                        ->where('ano_gravable', $fila['ano_gravable']);

                    if ($existente->exists()) {
                        $existente->update([
                            'valor_deducido' => $fila['valor_deducido'],
                            'origen' => $origen,
                            'id_usuario' => 0,
                            'fecha_registro' => $ahora,
                        ]);
                        $actualizadas++;
                    } else {
                        DB::table('det_fiscal_acumulado')->insert($fila + [
                            'id_corte_origen' => null,
                            'origen' => $origen,
                            'id_usuario' => 0,
                            'fecha_registro' => $ahora,
                        ]);
                        $insertadas++;
                    }
                }
            });
        } catch (\Exception $e) {
            $this->error('La carga se revirtió: ' . $e->getMessage());
            return 1;
        }

        $this->info("Leídas: $leidas · insertadas: $insertadas · actualizadas: $actualizadas");

        Deterioro::registrarBitacora('CARGAR_ACUMULADO_FISCAL', null, null, null,
            "$archivo · leídas $leidas, insertadas $insertadas, actualizadas $actualizadas", 0);

        return 0;
    }

    private function validar($fila)
    {
        if (count($fila) !== 3) {
            return 'se esperaban 3 columnas y llegaron ' . count($fila);
        }
        list($idOperacion, $ano, $valor) = array_map('trim', $fila);

        if (!ctype_digit($idOperacion) || (int) $idOperacion <= 0) {
            return "id_operacion inválido ($idOperacion)";
        }
        if (!ctype_digit($ano) || (int) $ano < 1900 || (int) $ano > 2999) {
            return "ano_gravable inválido ($ano)";
        }
        if (!is_numeric($valor)) {
            return "valor_deducido inválido ($valor)";
        }
        return null;
    }
}
