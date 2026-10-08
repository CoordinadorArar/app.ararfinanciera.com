<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class PHPMailerController extends Controller
{
    //
    public function email(){
        return view("procesos.email-body");
    }

    public static function crearEmail($data) {
        require base_path("vendor/autoload.php");
        $mail = new PHPMailer(true);     // Passing `true` enables exceptions
        try{
            // Email server settings
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = config('services.phpmailer.host');             //  smtp host
            $mail->SMTPAuth = true;
            $mail->Username = config('services.phpmailer.usuario');   //  sender username
            $mail->Password = config('services.phpmailer.clave');       // sender password
            $mail->SMTPSecure = config('services.phpmailer.encriptacion');                  // encryption - ssl/tls
            $mail->Port = config('services.phpmailer.port');                          // port - 587/465
            $mail->setFrom(config('services.phpmailer.usuario'), config('services.phpmailer.nombre_remitente'));
            $mail->addAddress($data['destinatario']);
            if($data['copiaA'] != ''){
                $mail->addCC($data['copiaA']);
            }
            if($data['emailBcc'] != ''){
                $mail->addBCC($data['emailBcc']);
            }
            $mail->addReplyTo(config('services.phpmailer.usuario'), config('services.phpmailer.nombre_remitente'));
            if(isset($_FILES['emailAttachments'])) {
                for ($i=0; $i < count($_FILES['emailAttachments']['tmp_name']); $i++) {
                    $mail->addAttachment($_FILES['emailAttachments']['tmp_name'][$i], $_FILES['emailAttachments']['name'][$i]);
                }
            }
            $mail->isHTML(true);                // Set email content format to HTML
            $mail->Subject = $data['asunto'];
            $mail->Body    = $data['cuerpo'];
            // $mail->AltBody = plain text version of email body;
 
            return $mail->send();
        }catch(Exception $e){
            report($e);
            return false;
        }
    }
}
