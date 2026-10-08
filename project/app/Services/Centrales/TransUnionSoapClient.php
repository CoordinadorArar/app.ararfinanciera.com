<?php

namespace App\Services\Centrales;

use DOMDocument;
use RobRichards\WsePhp\WSASoap;
use RobRichards\WsePhp\WSSESoap;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SoapClient;

class TransUnionSoapClient extends SoapClient
{
    private $llave;
    private $certificado;

    public function __construct($wsdl, array $opciones, $llave, $certificado)
    {
        $this->llave = $llave;
        $this->certificado = $certificado;
        parent::__construct($wsdl, $opciones);
    }

    #[\ReturnTypeWillChange]
    public function __doRequest($request, $location, $action, $version, $oneWay = 0)
    {
        $documento = new DOMDocument('1.0');
        $documento->loadXML($request);
        $wsa = new WSASoap($documento);
        $wsa->addAction($action);
        $wsa->addTo($location);
        $wsa->addMessageID();
        $wsa->addReplyTo();
        $wsse = new WSSESoap($wsa->getDoc());
        $wsse->signAllHeaders = true;
        $wsse->addTimestamp(10000);
        $llave = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $llave->loadKey($this->llave, true);
        $wsse->signSoapDoc($llave);
        $wsse->attachTokentoSig($wsse->addBinaryToken(file_get_contents($this->certificado)));
        return parent::__doRequest($wsse->saveXML(), $location, $action, $version, $oneWay);
    }
}
