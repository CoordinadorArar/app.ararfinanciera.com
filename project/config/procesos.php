<?php

return [
    'administrador' => 1,

    'estados' => [
        0 => 'Rechazado',
        1 => 'Registro',
        2 => 'Consulta centrales de riesgo',
        3 => 'Documentos de soporte',
        4 => 'Aprobación de crédito',
        5 => 'Aprobado',
    ],

    'transiciones' => [
        '1-2' => [1, 2, 6],
        '2-3' => [1, 2, 4, 5],
        '3-4' => [1, 2, 5],
        '4-5' => [1, 2, 3],
        '1-0' => [1, 2, 6],
        '2-0' => [1, 2, 4, 5],
        '3-0' => [1, 2, 5],
        '4-0' => [1, 2, 3],
    ],

    'motivoObligatorio' => [0],

    'motivoSinCupo' => 1,

    'acciones' => [
        'registro' => ['estados' => [1], 'roles' => [1, 2, 6]],
        'centrales' => ['estados' => [2], 'roles' => [1, 2, 4, 5]],
        'forzarConsultaCentrales' => ['estados' => [2], 'roles' => [1, 2]],
        'cargarDocumentos' => ['estados' => [3], 'roles' => [1, 2, 4, 6]],
        'aprobarDocumentos' => ['estados' => [3], 'roles' => [1, 2, 5]],
        'aprobarCredito' => ['estados' => [4], 'roles' => [1, 2, 3]],
        'editarCredito' => ['estados' => [4], 'roles' => [1, 2, 3]],
        'reenviarCorreo' => ['estados' => [0, 5], 'roles' => [1, 2, 3]],
    ],

    'bandeja' => [
        1 => [0, 1, 2, 3, 4, 5],
        2 => [0, 1, 2, 3, 4, 5],
        3 => [1, 2, 3, 4, 5],
        4 => [2, 3],
        5 => [2, 3, 4],
        6 => [0, 1, 2, 3, 4, 5],
    ],

    'soloPropios' => [6],
];
