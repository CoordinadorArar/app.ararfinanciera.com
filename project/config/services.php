<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Web service de SIESA (UnoEE). Se usa para importar documentos contables,
    | operaciones y terceros vía SOAP.
    */
    'siesa' => [
        'usuario' => env('SIESA_WS_USUARIO'),
        'clave' => env('SIESA_WS_CLAVE'),
        'conexion' => env('SIESA_WS_CONEXION', 'Real'),
        'id_cia' => env('SIESA_WS_ID_CIA', 7),
    ],

    /*
    | Cuenta SMTP que usa PHPMailerController. Es distinta de la del mailer de
    | Laravel (MAIL_*), por eso tiene sus propias claves.
    */
    'phpmailer' => [
        'host' => env('PHPMAILER_HOST', 'smtp.office365.com'),
        'port' => env('PHPMAILER_PORT', 587),
        'usuario' => env('PHPMAILER_USERNAME'),
        'clave' => env('PHPMAILER_PASSWORD'),
        'encriptacion' => env('PHPMAILER_ENCRYPTION', 'STARTTLS'),
        'nombre_remitente' => env('PHPMAILER_FROM_NAME', 'Arar Financiera'),
    ],

];
