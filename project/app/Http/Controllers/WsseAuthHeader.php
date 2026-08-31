<?php
    namespace WsseAuthHeader;
    use SoapHeader;
    use SoapVar;
    use stdClass;
    
    class WsseAuthHeader extends SoapHeader{
        private $wss_ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        //private $wsu_ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';

        function __construct($usuario,$contrasena,$ns = null){
            if($ns){
                $this->wss_ns = $ns;
            }
            $auth = new stdClass();
            $auth->Username = new SoapVar($usuario, XSD_STRING, NULL, $this->wss_ns,NULL,$this->wss_ns);
            $auth->Password = new SoapVar($contrasena, XSD_STRING, NULL, $this->wss_ns, NULL, $this->wss_ns);

            $token_usuario = new stdClass();
            $token_usuario->Username = new SoapVar($auth,SOAP_ENC_OBJECT,NULL,$this->wss_ns,'UsernameToken',$this->wss_ns);

            $security_sv = new SoapVar(
                new SoapVar($token_usuario, SOAP_ENC_OBJECT, NULL, $this->wss_ns, 'UsernameToken', $this->wss_ns),
                SOAP_ENC_OBJECT, NULL, $this->wss_ns, 'Security', $this->wss_ns);
            parent::__construct($this->wss_ns, 'Security', $security_sv, true);
        }
    }
?>