<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use App\Controllers\CorreoController;

class HomeController extends BaseController
{
    public function indexNew()
    {
        $data = array(
            'title' => 'Inicio'
        );
        return view('home', $data); // Reemplaza 'tu_vista' con el nombre de tu vista
    }

    public function indexFormulario()
    {
        $data = array(
            'title' => 'Simulador'
        );
        return view('simulador', $data); // Reemplaza 'tu_vista' con el nombre de tu vista
    }

    public function indexContacto()
    {
        $data = array(
            'title' => 'Contacto'
        );
        return view('contacto', $data); // Reemplaza 'tu_vista' con el nombre de tu vista
    }   
    
    public function indexQuienesSomos()
    {
        $data = array(
            'title' => '¿Quienes Somos?'
        );
        return view('quieneSomos', $data); // Reemplaza 'tu_vista' con el nombre de tu vista
    }
  
    public function indexProductos()
    {
        $data = array(
            'title' => 'Productos'
        );
        return view('productos', $data); // Reemplaza 'tu_vista' con el nombre de tu vista
    }

    public function correoSolicitud(){ 
      
        $pagaduria = $this->request->getVar('pagaduria');
        $asunto_correo = '';

        if (!empty($pagaduria)) {
            $nombre = $this->request->getVar('nombre');
            $documento = $this->request->getVar('cedula');
            $telefono = $this->request->getVar('celular');
            $correo = $this->request->getVar('correo');
            $asunto_correo = 'Solicitud Libranza Desde Pagina Web';
            $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background-color: #4073b9;
                    color: white;
                    padding: 20px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                }
                .content {
                    background-color: #f9f9f9;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 0 0 5px 5px;
                }
                .data-row {
                    margin-bottom: 5px;
                    padding: 10px;
                    background-color: white;
                    border-radius: 5px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .label {
                    font-weight: bold;
                    color: #4073b9;
                }
                .footer {
                    margin-top: 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Solicitud de Crédito de Libranza desde el home</h1>
            </div>
            <div class="content">
                <p>Se ha recibido una nueva solicitud de crédito con la siguiente información:</p>
                
                <div class="data-row">
                    <span class="label">Nombre Completo:</span><br>
                    ' . htmlspecialchars($nombre) . '
                </div>
                
                <div class="data-row">
                    <span class="label">Número de Documento:</span><br>
                    ' . htmlspecialchars($documento) . '
                </div>
                
                <div class="data-row">
                    <span class="label">Teléfono:</span><br>
                    ' . htmlspecialchars($telefono) . '
                </div>
                
                <div class="data-row">
                    <span class="label">Correo Electrónico:</span><br>
                    ' . htmlspecialchars($correo) . '
                </div>
                
                <div class="data-row">
                    <span class="label">Pagaduría:</span><br>
                    ' . htmlspecialchars($pagaduria) . '
                </div>
                
                <div class="footer">
                    <p>Este es un correo automático, por favor no responder.</p>
                    <p>© 2025 Arar Financiera - Todos los derechos reservados</p>
                </div>
            </div>
        </body>
        </html>';

        }else {
            $nombre = $this->request->getVar('nombreCompleto');
            $mensaje = $this->request->getVar('mensaje');
            $telefono = $this->request->getVar('telefono');
            $correo = $this->request->getVar('correo');
            $asunto = $this->request->getVar('asunto');

            $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background-color: #4073b9;
                    color: white;
                    padding: 20px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                }
                .content {
                    background-color: #f9f9f9;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 0 0 5px 5px;
                }
                .data-row {
                    margin-bottom: 5px;
                    padding: 10px;
                    background-color: white;
                    border-radius: 5px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .label {
                    font-weight: bold;
                    color: #4073b9;
                }
                .footer {
                    margin-top: 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Nuevo contacto desde la web</h1>
            </div>
            <div class="content">
                <p>Se ha recibido una nueva solicitud de crédito con la siguiente información:</p>
                
                <div class="data-row">
                    <span class="label">Asunto:</span><br>
                    ' . htmlspecialchars($asunto) . '
                </div>  
                
                <div class="data-row">
                    <span class="label">Nombre Completo:</span><br>
                    ' . htmlspecialchars($nombre) . '
                </div>
                
                <div class="data-row">
                    <span class="label">Mensaje:</span><br>
                    ' . htmlspecialchars($mensaje) . '
                </div>
                
                <div class="data-row">
                    <span class="label">Teléfono:</span><br>
                    ' . htmlspecialchars($telefono) . '
                </div>
                
                <div class="data-row">
                    <span class="label">Correo Electrónico:</span><br>
                    ' . htmlspecialchars($correo) . '
                </div>
                
                <div class="footer">
                    <p>Este es un correo automático, por favor no responder.</p>
                    <p>© 2025 Arar Financiera - Todos los derechos reservados</p>
                </div>
            </div>
        </body>
        </html>';

            $asunto_correo = $asunto;
        }

        $correos = array();

        if(!empty($correo)){
            array_push($correos, 'asesorcomercialinterno@ararfinanciera.com');
            // array_push($correos, 'coordinadordesarrollo@inversionesarar.com');
        }else{
            return $this->response->setJSON(['status' => 'error', 'messagge' => 'No se ha proporcionado un correo']);
        }

        $mail = new CorreoController();
        $envio = $mail->enviarCorreo($asunto_correo, $correos, $html);

        if($envio){
            return $this->response->setJSON(['status' => 'ok', 'messagge' => 'Correo enviado con información de solicitante']);
        }else{
            return $this->response->setJSON(['status' => 'error', 'messagge' => 'Algo falló al intentar realizar el envío']);
        }
    }

    public function politicaTratamientoDatos(){
        $data = array(
            'title' => 'Política de Tratamiento de Datos'
        );
        return view('tratamientoDatos', $data);
    }
}

?>