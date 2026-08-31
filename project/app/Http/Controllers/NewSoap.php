<?php
    // require __DIR__.'/vendor/autoload.php';
    
    use RobRichards\WsePhp\WSASoap;
    use RobRichards\WsePhp\WSSESoap;
    use RobRichards\XMLSecLibs\XMLSecurityKey;

    $certificado = getcwd().'/project/app/Http/Controllers/ararClave.pem';
    $key = getcwd().'/project/app/Http/Controllers/key.pem';
    define('PRIVATE_KEY', $key);
    define('CERT_FILE', $certificado);

    class NewSoap extends SoapClient{

        private $usuario;
        private $contrasena;
        private $hass_contrasena;

        public function addUserToken($username, $password, $digest = false){
            $this->usuario = $username;
            $this->contrasena = $password;
            $this->hass_contrasena = $digest;
        }

        public function __doRequest($request, $location, $action, $version=SOAP_1_2, $oneWay = 0){
            $doc = new \DOMDocument('1.0');
            $doc->loadXML($request);

            //$wsse = new WSSESoap($doc);
            $wsa = new WSASoap($doc);
            $wsa->addAction($action);
            $wsa->addTo($location);
            $wsa->addMessageID();
            $wsa->addReplyTo();

            $doc = $wsa->getDoc();
            $wsse = new WSSESoap($doc);

            $wsse->signAllHeaders = TRUE;

            //$wsse->signAllHeaders = true;

            $wsse->addTimestamp(10000);
            //$wsse->addUserToken($this->usuario, $this->contrasena, $this->hass_contrasena);

            $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type' => 'private'));
            $key->loadKey(PRIVATE_KEY, TRUE);

            $wsse->signSoapDoc($key);

            $token = $wsse->addBinaryToken(file_get_contents(CERT_FILE));
            $wsse->attachTokentoSig($token);

            $request = $wsse->saveXML();
            return parent::__doRequest($request, $location, $action, $version);
        }
    }
?>