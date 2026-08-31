<?php

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AdminController;
//use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ContableController;
use App\Http\Controllers\DeterioroController;
use App\Http\Controllers\GestionDocumentalController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PetitionsController;
use App\Http\Controllers\ProcesosController;
use App\Http\Controllers\RoutesController;
use App\Http\Controllers\TerceroSiesaController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/',[HomeController::class, 'index']);

/**Rutas de sesión de usuario */
Auth::routes();
Route::post('/validar-estado-usuario',[UserController::class, 'validarInfoSesionUsuario'])->name('validar-estado-usuario');
Route::get('/log-out',[UserController::class, 'logout'])->name('log-out');
//Route::post('/auth/login',[LoginController::class, 'authenticated'])->name('authenticated');

/**Rutas de vistas */
Route::group(['middleware'=>['auth','submenu.permiso']],function(){
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/form-crear-tercero', [RoutesController::class, 'vistaRegistroInformacion'])->name('form-crear-tercero');
    Route::get('/simulacion-credito', [RoutesController::class, 'calcularVista'])->name('simulacion-credito');
    Route::get('/crear-cliente-siesa', [RoutesController::class, 'crearClienteSiesa'])->name('crear-cliente-siesa');
    //Route::get('/support-docs', [RoutesController::class, 'supportDocs'])->name('support-docs');
    Route::get('/lista-procesos', [RoutesController::class, 'listaProcesos'])->name('lista-procesos');
    Route::get('/documento-contable', [RoutesController::class, 'documentoContable'])->name('documento-contable');
    Route::get('/documento-operaciones', [RoutesController::class, 'documentoOperaciones'])->name('documento-operaciones');
    Route::get('/reclasificar-operaciones', [RoutesController::class, 'reclasificarOperaciones'])->name('reclasificar-operaciones');
    Route::get('/cupones', [RoutesController::class, 'cupones'])->name('cupones');
    Route::get('/gestion-usuarios', [RoutesController::class, 'GestionUsuarios'])->name('gestion-usuarios');
    Route::get('/gestion-asesores', [RoutesController::class, 'GestionAsesores'])->name('gestion-asesores');
    Route::get('/crear-pagadurias', [RoutesController::class, 'crearPagaduria'])->name('crear-pagadurias');
    Route::get('/perfil-usuario', [RoutesController::class, 'perfilUsuario'])->name('perfil-usuario');
    Route::get('/gestion-sitio', [RoutesController::class, 'gestionSitio'])->name('gestion-sitio');
    Route::get('/consulta-folios', [RoutesController::class, 'consultaFolios'])->name('consulta-folios');
    Route::get('/digitalizar-facturas', [RoutesController::class, 'digitalizarFacturas'])->name('digitalizar-facturas');
    Route::get('/gestion-cajas', [RoutesController::class, 'gestionCajas'])->name('gestion-cajas');
    Route::get('/tablero-digitalizacion', [RoutesController::class, 'tableroDigitalizacion'])->name('tablero-digitalizacion');
    Route::get('/deterioro-cortes', [RoutesController::class, 'deterioroCortes'])->name('deterioro-cortes');
    Route::get('/deterioro-resumen', [RoutesController::class, 'deterioroResumen'])->name('deterioro-resumen');
    Route::get('/deterioro-detalle-operaciones', [RoutesController::class, 'deterioroDetalleOperaciones'])->name('deterioro-detalle-operaciones');
});

/**Rutas Gestión Documental */
Route::group(['middleware'=>'auth'],function(){
    Route::post('/consultar-folios', [GestionDocumentalController::class, 'consultarFolios'])->name('consultar-folios');
    Route::post('/facturas-pendientes-digitalizar', [GestionDocumentalController::class, 'facturasPendientesDigitalizar'])->name('facturas-pendientes-digitalizar');
    Route::post('/estadisticas-digitalizar', [GestionDocumentalController::class, 'estadisticasDigitalizar'])->name('estadisticas-digitalizar');
    Route::post('/digitalizar-factura', [GestionDocumentalController::class, 'digitalizarFactura'])->name('digitalizar-factura');
    Route::post('/cajas-en-proceso', [GestionDocumentalController::class, 'cajasEnProceso'])->name('cajas-en-proceso');
    Route::post('/cajas-pendientes-ubicacion', [GestionDocumentalController::class, 'cajasPendientesUbicacion'])->name('cajas-pendientes-ubicacion');
    Route::post('/crear-caja', [GestionDocumentalController::class, 'crearCaja'])->name('crear-caja');
    Route::post('/ubicar-caja', [GestionDocumentalController::class, 'ubicarCaja'])->name('ubicar-caja');
    Route::get('/ver-documento-folio/{idFolio}', [GestionDocumentalController::class, 'verDocumentoFolio'])->name('ver-documento-folio');
});

/**Rutas de administrador */
Route::group(['middleware'=>'auth'],function(){
    Route::post('/menus',[AdminController::class, 'obtenerMenus'])->name('get-menus');
    Route::post('/submenus',[AdminController::class, 'obtenerSubMenus'])->name('get-submenus');
    Route::post('/informacion-usuario', [UserController::class, 'obtenerRol'])->name('get-rol');
    Route::post('/editar-datos-usuario', [AdminController::class, 'editarUsuario'])->name('editar-datos-usuario');
    Route::post('/editar-contrasena', [AdminController::class, 'editarContrasena'])->name('editar-contrasena');
    Route::post('/subir-foto-perfil', [AdminController::class, 'subirFotoPerfil'])->name('subir-foto-perfil');
    Route::get('/mostrar-foto-perfil/{imagen}', [AdminController::class, 'mostrarFotoPerfil'])->name('mostrar-foto-perfil');
    /**Gestion de usuarios */
    Route::post('/mostrar-info-usuario', [AdminController::class, 'mostrarInfoUsuario'])->name('mostrar-info-usuario');
    Route::post('/editar-usuarios', [AdminController::class, 'editarUsuarios'])->name('editar-usuarios');
    /**Gestion de asesores */
    Route::post('/mostrar-procesos-asesor', [AdminController::class, 'mostrarProcesosAsesor'])->name('mostrar-procesos-asesor');
    Route::post('/proceso-solo-info', [AdminController::class, 'procesoSoloInfo'])->name('proceso-solo-info');
    /**Gestión de pagadurias */
    Route::post('/guardar-pagaduria', [AdminController::class, 'guardarPagaduria'])->name('guardar-pagaduria');
    Route::post('/mostrar-info-pagaduria', [AdminController::class, 'mostrarInfoPagaduria'])->name('mostrar-info-pagaduria');
    Route::post('/cantidad-config-pagaduria', [AdminController::class, 'cantidadConfigPagaduria'])->name('cantidad-config-pagaduria');
    Route::post('/mostrar-rubros-pagaduria', [AdminController::class, 'mostrarRubrosPagaduria'])->name('mostrar-rubros-pagaduria');
    Route::post('/guardar-pagaduria-info', [AdminController::class, 'guardarPagaduriaInfo'])->name('guardar-pagaduria-info');
    Route::post('/guardar-rubro-configuracion', [AdminController::class, 'guardarRubroConfiguracion'])->name('guardar-rubro-configuracion');
    /**Gestión de roles, variables y mas elementos del aplicativo... */
    Route::post('/mostrar-info-admin', [AdminController::class, 'mostrarInfoAdmin'])->name('mostrar-info-admin');
    Route::post('/guardar-datos-sitio', [AdminController::class, 'guardarDatosSitio'])->name('guardar-datos-sitio');
});
/**Rutas de peticiones multiples */
Route::group(['middleware'=>'auth'],function(){
    Route::post('/mostrar-tipos-documentos', [PetitionsController::class, 'mostrarTiposDocumentos'])->name('mostrar-tipos-documentos');
    Route::post('/mostrar-pagadurias', [PetitionsController::class, 'mostrarPagadurias'])->name('mostrar-pagadurias');
    Route::post('/mostrar-departamentos', [PetitionsController::class, 'mostrarDepartamentos'])->name('mostrar-departamentos');
    Route::post('/mostrar-ciudades', [PetitionsController::class, 'mostrarCiudades'])->name('mostrar-ciudades');
    Route::post('/mostrar-roles', [PetitionsController::class, 'mostrarRoles'])->name('mostrar-roles');
});
/**Rutas de registro de datos*/
Route::group(['middleware'=>'auth'],function(){
    Route::post('/validar-documento', [ProcesosController::class, 'validarDocumento'])->name('validar-documento');
    Route::post('/guardar-datos-personales', [ProcesosController::class, 'guardarDatosPersonales'])->name('guardar-datos-personales');
    Route::post('/mostrar-config-inputs', [ProcesosController::class, 'mostrarConfigInputs'])->name('mostrar-config-inputs');
    Route::post('/verificar-edad-tercero', [ProcesosController::class, 'verificarEdadTercero'])->name('verificar-edad-tercero');
    Route::post('/guardar-datos-financieros', [ProcesosController::class, 'guardarDatosFinancieros'])->name('guardar-datos-financieros');
    Route::post('/editar-estado-proceso', [ProcesosController::class, 'editarEstadoProceso'])->name('editar-estado-proceso');
    Route::post('/enviar-email-aprobacion-datos', [ProcesosController::class, 'enviarEmailAprobacionDatos'])->name('enviar-email-aprobacion-datos');
    Route::post('/subir-archivo-tratamiento-datos', [ProcesosController::class, 'subirArchivoTratamientoDatos'])->name('subir-archivo-tratamiento-datos');
});
//ruta de generación de formato de autorización digital
Route::group(['middleware'=>['CORS']],function(){
    Route::get('/generar-formato-autorizacion/{idProceso}/{permisos}', [ProcesosController::class, 'generarFormatoAutorizacion'])->name('generar-formato-autorizacion');
});
Route::get('/aceptar-tratamiento-datos/{idProceso}/{documento}', [ProcesosController::class, 'aceptarTratamientoDatos'])->name('aceptar-tratamiento-datos');
/**Rutas Perfilamiento */
Route::group(['middleware'=>'auth'],function(){
    //Route::post('/show-active-process', [ProcesosController::class, 'showActiveProcess'])->name('show-active-process');
    Route::post('/mostrar-valores-proceso', [ProcesosController::class, 'mostrarValoresProceso'])->name('mostrar-valores-proceso');/**eliminar ruta */
    //Route::post('/calculate', [ProcesosController::class, 'calculate'])->name('calculate');
    Route::post('/iniciar-proceso-credito', [ProcesosController::class, 'iniciarProcesoCredito'])->name('iniciar-proceso-credito');
    Route::post('/validar-meses',[ProcesosController::class, 'validarMeses'])->name('validar-meses');
    Route::post('/simulacion-credito',[ProcesosController::class, 'simulacionCredito'])->name('simulacion-credito');
});
/**Rutas creación de cliente y relacionados en SIESA */
Route::group(['middleware' => 'auth'],function(){
    Route::post('/creacion-tercero-siesa', [TerceroSiesaController::class, 'creacionTerceroSiesa'])->name('creacion-tercero-siesa');
    Route::post('/creacion-cliente-siesa', [TerceroSiesaController::class, 'creacionClienteSiesa'])->name('creacion-cliente-siesa');
    Route::post('/creacion-proveedor', [TerceroSiesaController::class, 'creacionProveedor'])->name('creacion-proveedor');
    Route::post('/creacion-impretension', [TerceroSiesaController::class, 'creacionImpretension'])->name('creacion-impretension');
    Route::post('/creacion-impretencion-proveedor', [TerceroSiesaController::class, 'creacionImpretencionProveedor'])->name('creacion-impretencion-proveedor');
    Route::post('/crear-pagoelec-bancolombia', [TerceroSiesaController::class, 'crearPagoelecBancolombia'])->name('crear-pagoelec-bancolombia');
    Route::post('/crear-pagoelec-bancobogota', [TerceroSiesaController::class, 'crearPagoelecBancobogota'])->name('crear-pagoelec-bancobogota');
});
/**Rutas de procesos, estados y vistas relacionadas */
Route::group(['middleware'=>'auth'],function(){
    Route::post('/lista-procesos-filtro', [ProcesosController::class, 'listaProcesos'])->name('lista-procesos-filtro');
    Route::post('/mostrar-info-proceso', [ProcesosController::class, 'mostrarInfoProcesos'])->name('mostrar-info-proceso');
    //Route::post('/verify-documents-all-procesos', [ProcesosController::class, 'verifyDocumentsAllprocesos'])->name('verify-documents-all-procesos');
    Route::post('/consulta-centrales-riesgo', [ProcesosController::class, 'consultaCentralesRiesgo'])->name('consulta-centrales-riesgo');
    Route::post('/verificar-todos-los-documentos', [ProcesosController::class, 'verificarDocumentos'])->name('verificar-todos-los-documentos');
    Route::post('/subir-documentos-soporte', [ProcesosController::class, 'subirDocumentosSoporte'])->name('subir-documentos-soporte');
    Route::get('/ver-documento-soporte/{nombreDocumento}/{idProceso}', [ProcesosController::class, 'verDocumentoSoporte'])->name('ver-documento-soporte');
    Route::post('/gestion-documentos-proceso', [ProcesosController::class, 'gestionDocumentosSoporte'])->name('gestion-documentos-proceso');
    Route::post('/enviar-email-credito', [ProcesosController::class, 'enviarEmailCredito'])->name('enviar-email-credito');
    Route::post('/editar-proceso',[ProcesosController::class, 'editarProceso'])->name('editar-proceso');
    Route::get('/descargar-info-credito/{idProceso}', [ProcesosController::class, 'descargarInfoCredito'])->name('descargar-info-credito');
});
/**Rutas vistas contables */
Route::group(['middleware'=>'auth'],function(){
    Route::post('/documento-contable-filtros', [ContableController::class, 'documentoContableFiltros'])->name('documento-contable-filtros');
    Route::post('/documento-contable-envio-siesa', [ContableController::class, 'documentoContableEnvioSiesa'])->name('documento-contable-envio-siesa');
    Route::post('/nota-credito-contable-envio-siesa', [ContableController::class, 'NotaCreditoContableEnvioSiesa'])->name('nota-credito-contable-envio-siesa');
    Route::post('/reclasificar-filtros', [ContableController::class, 'reclasificarFiltros'])->name('reclasificar-filtros');
    Route::post('/reclasificar-op-envio-siesa', [ContableController::class, 'reclasificarOpEnvioSiesa'])->name('reclasificar-op-envio-siesa');
    Route::post('/documento-operaciones-filtros', [ContableController::class, 'documentoOperacionesFiltros'])->name('documento-operaciones-filtros');
    Route::post('/documento-operaciones-envio-siesa', [ContableController::class, 'documentoOperacionesEnvioSiesa'])->name('documento-operaciones-envio-siesa');
    Route::post('/generar-cupones-archivo', [ContableController::class, 'generarCuponesArchivo'])->name('generar-cupones-archivo');
});
/**Rutas deterioro de cartera
 * A diferencia del resto del sitio, estas acciones sí verifican permiso por
 * operación y no solo autenticación: crear, ejecutar o eliminar un corte tiene
 * efecto contable. */
Route::group(['middleware'=>['auth','deterioro.permiso:consultar']],function(){
    Route::post('/deterioro-periodo-origen', [DeterioroController::class, 'periodoOrigen'])->name('deterioro-periodo-origen');
    Route::post('/deterioro-listar-cortes', [DeterioroController::class, 'listarCortes'])->name('deterioro-listar-cortes');
    Route::post('/deterioro-resumen-datos', [DeterioroController::class, 'resumenCorte'])->name('deterioro-resumen-datos');
    Route::post('/deterioro-detalle-datos', [DeterioroController::class, 'detalleOperaciones'])->name('deterioro-detalle-datos');
    Route::post('/deterioro-cuotas-operacion', [DeterioroController::class, 'cuotasOperacion'])->name('deterioro-cuotas-operacion');
});
Route::group(['middleware'=>['auth','deterioro.permiso:calcular']],function(){
    Route::post('/deterioro-crear-corte', [DeterioroController::class, 'crearCorte'])->name('deterioro-crear-corte');
    Route::post('/deterioro-ejecutar-corte', [DeterioroController::class, 'ejecutarCorte'])->name('deterioro-ejecutar-corte');
    Route::post('/deterioro-eliminar-corte', [DeterioroController::class, 'eliminarCorte'])->name('deterioro-eliminar-corte');
});