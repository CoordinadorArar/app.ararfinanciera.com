<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
//$routes->get('/', 'Home::index');

//RUTA PARA EL HOME
$routes->get('/', 'HomeController::indexNew');
//RUTA PARA FORMULARIO
$routes->get('simulador', 'HomeController::indexFormulario');
//Ruta para contacto
$routes->get('contacto', 'HomeController::indexContacto');
//Ruta para quienes somos 
$routes->get('quieneSomos', 'HomeController::indexQuienesSomos');
//Ruta para productos
$routes->get('productos', 'HomeController::indexProductos');
//Rutas calculos de creditos
$routes->post('simulador/creditos/calcular', 'SimuladorController::calcularValorCuotas');
//Ruta pólitica tratamiento datos
$routes->get('politica-tratamiento-datos', 'HomeController::politicaTratamientoDatos');

//Rutas del backend
$routes->post('envioCorreoSolicitud', 'HomeController::correoSolicitud');
$routes->post('envioCorreoContacto', 'HomeController::correoSolicitud');
