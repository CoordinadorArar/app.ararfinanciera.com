<?php

namespace App\Models;

use CodeIgniter\Model;

class SimuladorModel extends Model
{
    protected $useAutoIncrement = false;
    protected $returnType = 'array';

    /**
     * Método para consultar la tasa de interes para calcular las cuotas.
     * 
     * @return array Lista de precios.
     */
    public function obtenerTasaInteres(): array
    {
        $builder = $this->db->table('ValoresVariables');
        $builder->select(
            'ValorVariable'
        );
        $query = $builder->get();

        return $query->getResultArray();
    }

}
