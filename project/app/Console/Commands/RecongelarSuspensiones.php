<?php

namespace App\Console\Commands;

use App\Models\Deterioro;
use App\Support\Ambiente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula `interes_congelado` de las marcas vigentes con la regla de D-06
 * definida por Contabilidad el 17 de septiembre de 2026: el interés son las
 * `FAT` de la operación hasta el mes del corte del evento, por su saldo
 * pendiente a esa fecha.
 *
 * Existe porque el valor **está guardado**, no se recalcula. `aplicarSuspensiones()`
 * lee `det_suspension_interes.interes_congelado` tal como está en la tabla, de
 * modo que cambiar la regla en el código no mueve ni un peso de un corte hasta
 * que las marcas se vuelven a congelar. El cargue inicial también las refresca,
 * pero reescribe causal, fecha y observación desde el CSV y no alcanza a las
 * marcas manuales; este comando toca **una sola columna** y las cubre todas.
 *
 * Deja en bitácora una línea por marca modificada, con el valor anterior y el
 * nuevo, porque cambia la base de deterioro de operaciones ya calculadas.
 *
 * La única escritura que **no** se audita es el vaciado de `id_corte_congelado`
 * en las marcas vigentes. Es deliberado: con la regla de las `FAT` esa columna
 * dejó de tener significado —no hay corte de anclaje— y anularla no mueve
 * ninguna cifra. Se deja dicho aquí para que nadie la busque en la bitácora.
 *
 * Después de correrlo hay que **recalcular los cortes abiertos o calculados**
 * para que la nueva base se refleje. Los cortes cerrados no cambian: su foto
 * quedó congelada y el módulo no los recalcula.
 */
class RecongelarSuspensiones extends Command
{
    protected $signature = 'deterioro:recongelar-suspensiones {--ambiente=produccion} {--simular}';

    protected $description = 'Recalcula el interés congelado de las marcas vigentes con la regla de las FAT (D-06)';

    public function handle()
    {
        $ambiente = Ambiente::aplicar($this->option('ambiente'));
        $this->info('Ambiente: '.$ambiente.' · base '.config('database.connections.sqlsrv.database'));

        $marcas = DB::table('det_suspension_interes')
            ->whereNull('fecha_reactivacion')
            ->orderBy('id_operacion')
            ->get(['id_suspension', 'id_operacion', 'fecha_evento', 'interes_congelado',
                   'id_corte_congelado', 'origen']);

        if ($marcas->isEmpty()) {
            $this->warn('No hay marcas vigentes. Nada que hacer.');
            return 0;
        }

        $nuevos = Deterioro::interesALaFechaDelEventoEnLote($marcas->map(function ($m) {
            return ['id_operacion' => $m->id_operacion, 'fecha_evento' => $m->fecha_evento];
        })->all());

        $iguales = 0;
        $cambios = [];
        $aparecen = 0;
        $desaparecen = 0;
        $totalAntes = 0;
        $totalDespues = 0;

        foreach ($marcas as $m) {
            $nuevo = $nuevos[Deterioro::claveMarcaCongelada($m->id_operacion, $m->fecha_evento)];
            $antes = $m->interes_congelado;
            $despues = $nuevo['interes_congelado'];

            $totalAntes += $antes === null ? 0 : (float) $antes;
            $totalDespues += $despues === null ? 0 : (float) $despues;

            // La comparación es por valor y no por identidad: la columna es
            // decimal y vuelve como cadena ('12591.0000'), de modo que un ===
            // marcaría como cambio lo que no lo es. Nulo se compara aparte
            // porque nulo y cero son estados distintos en esta columna.
            if ($antes === null && $despues === null) {
                $iguales++;
                continue;
            }
            if ($antes !== null && $despues !== null && abs((float) $antes - (float) $despues) < 0.005) {
                $iguales++;
                continue;
            }

            if ($antes === null) {
                $aparecen++;
            } elseif ($despues === null) {
                $desaparecen++;
            }

            $cambios[] = [
                'marca' => $m,
                'antes' => $antes,
                'despues' => $despues,
                'facturas' => $nuevo['facturas_fat'],
            ];
        }

        $anclas = $marcas->whereNotNull('id_corte_congelado')->count();

        $this->imprimirResumen($marcas->count(), $iguales, count($cambios), $aparecen, $desaparecen,
            $totalAntes, $totalDespues, $anclas, $cambios);

        if ($this->option('simular')) {
            $this->info('--simular: no se escribió nada.');
            return 0;
        }
        // El ancla cuenta como trabajo pendiente: si no se mirara, el resumen
        // prometería limpiarla y el comando saldría sin hacerlo.
        if (!$cambios && !$anclas) {
            $this->info('Ninguna marca cambia. No se escribió nada.');
            return 0;
        }

        DB::transaction(function () use ($cambios, $marcas) {
            // El ancla se limpia en TODAS las vigentes, no sólo en las que cambian
            // de valor. Con la regla de las FAT ya no hay corte de anclaje, así
            // que una marca con valor nuevo y ancla vieja dejaría la columna sin
            // significado: mirándola no se sabría con qué regla se congeló.
            DB::table('det_suspension_interes')
                ->whereIn('id_suspension', $marcas->pluck('id_suspension')->all())
                ->whereNotNull('id_corte_congelado')
                ->update(['id_corte_congelado' => null]);

            foreach ($cambios as $c) {
                DB::table('det_suspension_interes')
                    ->where('id_suspension', $c['marca']->id_suspension)
                    ->update(['interes_congelado' => $c['despues'], 'id_corte_congelado' => null]);

                Deterioro::registrarBitacora('RECONGELAR_SUSPENSION', null, $c['marca']->id_operacion,
                    $c['antes'] === null ? 'sin congelar' : $c['antes'],
                    $c['despues'] === null
                        ? 'sin congelar, sin FAT hasta el mes del evento'
                        : $c['despues'].' desde '.$c['facturas'].' FAT', 0);
            }
        });

        if ($cambios) {
            $this->info(count($cambios).' marca(s) actualizada(s), cada una con su línea en bitácora.');
            $this->warn('Recalcule los cortes no cerrados para que la nueva base se refleje en el deterioro.');
        } else {
            // Sin cambios de valor no hay nada que recalcular: se anuló una
            // columna que ya no significa nada y ninguna cifra se movió.
            $this->info($anclas.' marca(s) quedaron sin corte de anclaje. Ninguna cifra cambió.');
        }

        return 0;
    }

    private function imprimirResumen($total, $iguales, $cambian, $aparecen, $desaparecen,
        $totalAntes, $totalDespues, $anclas, $cambios)
    {
        $peso = function ($v) {
            return $v === null ? 'sin congelar' : number_format((float) $v, 2, ',', '.');
        };

        $this->info("Marcas vigentes: $total · sin cambio: $iguales · cambian: $cambian");
        $this->line("  - pasan de sin congelar a tener valor: $aparecen");
        $this->line("  - pasan de tener valor a sin congelar: $desaparecen");
        $this->line('  - interés congelado total antes:   '.$peso($totalAntes));
        $this->line('  - interés congelado total después: '.$peso($totalDespues));
        $this->line('  - diferencia:                      '.$peso($totalDespues - $totalAntes));
        if ($anclas) {
            $this->line("  - marcas que aún llevan corte de anclaje de la regla anterior: $anclas (se limpia)");
        }

        if (!$cambios) {
            return;
        }

        // Las que pierden el valor van completas: dejan de estar suspendidas a
        // efectos del cálculo y hay que poder revisarlas una por una.
        $pierden = array_filter($cambios, function ($c) {
            return $c['despues'] === null;
        });
        if ($pierden) {
            $this->warn('Marcas que quedan sin congelar y volverán a deteriorarse con la base normal:');
            foreach ($pierden as $c) {
                $this->line("  - operación {$c['marca']->id_operacion} ({$c['marca']->origen}), tenía "
                    .$peso($c['antes']));
            }
        }

        $mayores = array_filter($cambios, function ($c) {
            return $c['despues'] !== null && $c['antes'] !== null;
        });
        usort($mayores, function ($a, $b) {
            return abs((float) $b['despues'] - (float) $b['antes']) <=> abs((float) $a['despues'] - (float) $a['antes']);
        });
        if ($mayores) {
            $this->info('Las diez variaciones mayores sobre marcas que ya tenían valor:');
            foreach (array_slice($mayores, 0, 10) as $c) {
                $this->line("  - operación {$c['marca']->id_operacion}: ".$peso($c['antes']).' → '
                    .$peso($c['despues']).' ('.$c['facturas'].' FAT)');
            }
        }
    }
}
