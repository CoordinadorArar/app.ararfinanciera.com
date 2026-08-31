<?php
    namespace App\Controllers;

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    use App\Controllers\BaseController;
    
    class CorreoController extends BaseController
    {
        public function enviarCorreo($asunto, $correos, $cuerpo)
        {
            $mail = new PHPMailer(true);  // El `true` activa excepciones
    
            try {
                // Configuración del servidor
                $mail->isSMTP();
                $mail->Host = 'smtp.office365.com';
                $mail->SMTPDebug = 0; // Cambiado a 0 para no mostrar debug
                $mail->SMTPAuth = true;
                $mail->Username = 'redessociales@inversionesarar.com';
                $mail->Password = 'StrykeNc@';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->CharSet = 'UTF-8';
                $mail->Port = 587;

                // Remitente y destinatario
                $mail->setFrom('redessociales@inversionesarar.com', 'Arar Financiera');

                if(is_array($correos)){
                    foreach($correos as $correo){
                        $mail->addAddress($correo);
                    }
                }
    
                // Contenido
                $mail->isHTML(true);
                $mail->Subject = $asunto;
                $mail->Body = $cuerpo;
                $mail->AltBody = strip_tags($cuerpo);
    
                if($mail->send()) {
                    return true;
                } else {
                    log_message('error', 'Error al enviar correo: ' . $mail->ErrorInfo);
                    return false;
                }
            } catch (Exception $e) {
                log_message('error', 'Excepción al enviar correo: ' . $e->getMessage());
                return false;
            }
        }
    }    
?>