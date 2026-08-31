<?php
namespace App\Controllers;

use App\Controllers\BaseController;


use App\Models\SimuladorModel;
use DateTime;

class SimuladorController extends BaseController
{

    protected $simuladorModel;


       /**
     * Metodo constructor.
     */
    function __construct()
    {
    
        $this->simuladorModel = new SimuladorModel();


    }

    

    public function calcularValorCuotas()
    {

        $request = $this->request;
        $data = $request->getJSON();

        $periodoCredito = $data->periodoCredito ?? null;
        $info = $data->info ?? null;
        //$tasaInteres = $data->tasaInteres ?? null;
        $valorCredito = $data->valorCredito ?? null;
        $edadFecha = $data->edadFecha ?? null;

        $res = $this->simuladorModel->obtenerTasaInteres();
       

        $tasaInteres = $res[0]['ValorVariable'];
        
    
        $deuda = str_replace(["$", " ", ","], "", $valorCredito);

        if ($info != '') {
            $deuda1 = str_replace(["$", " "], ["$&nbsp;", ""], $valorCredito);
            $deuda1 = str_replace(",", ".", $deuda1);
        
            $interes = $tasaInteres / 100;
            $calculo = (int)($deuda * $interes * (pow((1 + $interes), ($periodoCredito)))) / ((pow((1 + $interes), ($periodoCredito))) - 1);

            $seguros = ($edadFecha < 75) ? $valorCredito * 0.0030 : $valorCredito * 0.00562;

            $html = '<tr>
                        <td><strong>Valor Crédito : ' . $deuda1 . ' </strong></td>
                    </tr>
                    <tr>
                        <td><strong>Valor Cuota Mensual : $ ' . number_format($calculo + $seguros, 2, ",", ".") . '</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Número Cuota : ' . $periodoCredito . ' </strong></td>
                    </tr>
                    <tr>
                        <td><strong>Tasa Mensual : ' . $tasaInteres . ' % </strong></td>
                    </tr>
                    <tr style="border-top: 0.5px solid;">
                        <td><h2 style="font-size: 18px;font-weight: 700;">Tabla Simulación de crédito</h2></td>
                    </tr>';

            return $this->response->setJSON([
                'html' => $html,
                'datos' => ['cuota' => (int)$calculo + $seguros]
            ]);
        } else {
            $html = '';
            $totalint = 0;

            if (is_numeric($deuda) && is_numeric($periodoCredito)) {
               
                $interes = $tasaInteres / 100;
                $seguros = ($edadFecha < 75) ? $valorCredito * 0.0030 : $valorCredito * 0.005625;

                $calculo = (int)($deuda * $interes * (pow((1 + $interes), ($periodoCredito)))) / ((pow((1 + $interes), ($periodoCredito))) - 1);

                for ($i = 1; $i <= $periodoCredito; $i++) {
                    $totalint += ($deuda * $interes);
                    $html .= '<tr>
                                <td>' . $i . '</td>
                                <td>' . number_format($calculo, 2, ",", ".") . '</td>
                                <td>' . number_format($calculo - ($deuda * $interes), 2, ",", ".") . '</td>
                                <td>' . number_format($deuda * $interes, 2, ",", ".") . '</td>
                                <td>' . number_format($seguros, 2, ",", ".") . '</td>
                                <td>' . number_format($calculo + $seguros, 2, ",", ".") . '</td>';

                    $deuda -= ($calculo - ($deuda * $interes));

                    $html .= ($deuda < 0) ? '<td>0</td>' : '<td>' . number_format($deuda, 2, ",", ".") . '</td>';
                    $html .= '</tr>';
                }

                return $this->response->setJSON(['html' => $html]);
            } else {
                return $this->response->setJSON([
                    'error' => 'Parece haber un problema con los valores enviados para la simulación'
                ]);
            }
        }

      
    }






}







?>