<?php

return [
    'predeterminado' => env('CENTRALES_PREDETERMINADO') ?: 'transunion',

    'vigencia_dias' => (int) env('CENTRALES_VIGENCIA_DIAS') ?: 30,

    'simulado_habilitado' => in_array(env('CENTRALES_SIMULADO'), [null, ''], true) ? env('APP_ENV') === 'local' : (bool) env('CENTRALES_SIMULADO'),

    'proveedores' => [
        'transunion' => [
            'nombre' => 'TransUnion',
            'clase' => App\Services\Centrales\TransUnionProveedor::class,
            'ambiente' => env('TRANSUNION_AMBIENTE') ?: 'pruebas',
            'wsdl' => env('TRANSUNION_WSDL', ''),
            'usuario' => env('TRANSUNION_USER', ''),
            'password' => env('TRANSUNION_PASSWORD', ''),
            'key_path' => env('TRANSUNION_KEY_PATH', ''),
            'cert_path' => env('TRANSUNION_CERT_PATH', ''),
            'codigo_informacion' => env('TRANSUNION_CODIGO_INFORMACION') ?: '5702',
            'motivo_consulta' => env('TRANSUNION_MOTIVO_CONSULTA') ?: '1',
            'timeout' => (int) env('TRANSUNION_TIMEOUT') ?: 30,
            'verify_peer' => env('TRANSUNION_VERIFY_PEER') !== false,
            'montos_en_miles' => env('TRANSUNION_MONTOS_EN_MILES') !== false,
            'tipos_documento' => [1 => '1'],
        ],
        'datacredito' => [
            'nombre' => 'DataCrédito (Experian)',
            'clase' => App\Services\Centrales\DatacreditoProveedor::class,
            'pendiente_validar' => 'Adaptador HC2 pendiente de validar con la especificación oficial de Experian.',
            'ambiente' => env('DATACREDITO_AMBIENTE') ?: 'pruebas',
            'endpoint' => env('DATACREDITO_ENDPOINT', ''),
            'usuario' => env('DATACREDITO_USUARIO', ''),
            'password' => env('DATACREDITO_PASSWORD', ''),
            'codigo_suscriptor' => env('DATACREDITO_CODIGO_SUSCRIPTOR', ''),
            'producto' => env('DATACREDITO_PRODUCTO') ?: '64',
            'operacion' => env('DATACREDITO_OPERACION') ?: 'consultarHC2',
            'namespace' => env('DATACREDITO_NAMESPACE') ?: 'http://ws.hc2.dc.com/v1',
            'cert_path' => env('DATACREDITO_CERT_PATH', ''),
            'key_path' => env('DATACREDITO_KEY_PATH', ''),
            'key_password' => env('DATACREDITO_KEY_PASSWORD', ''),
            'timeout' => (int) env('DATACREDITO_TIMEOUT') ?: 30,
            'verify_peer' => env('DATACREDITO_VERIFY_PEER') !== false,
            'montos_en_miles' => env('DATACREDITO_MONTOS_EN_MILES') !== false,
            'tipos_documento' => [1 => env('DATACREDITO_TIPO_IDENTIFICACION') ?: '1'],
        ],
        'simulado' => [
            'nombre' => 'Simulado (pruebas)',
            'clase' => App\Services\Centrales\SimuladoProveedor::class,
            'ambiente' => 'simulado',
        ],
    ],
];
