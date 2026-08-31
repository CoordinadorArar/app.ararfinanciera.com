<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

class DB_Manager_Arar {
    protected $SYSServer;
    protected $connectionInfo;
    protected $conexion;
    private   $_parameters;
    private   $_objPdo;
    
    function __construct(){
		$cfg = require __DIR__.'/../config.php';
		$SYSServer = $cfg['sqlserver']['host'];
		$connectionInfo = array( "Database"=>"ArarFinanciera", "UID"=>$cfg['sqlserver']['usuario'], "PWD"=>$cfg['sqlserver']['clave']);
		$conexion = sqlsrv_connect( $SYSServer, $connectionInfo);

		if (!$conexion) {
			echo "Connection error.<br />";
	     	die( print_r( sqlsrv_errors(), true));
		} 
		$this->_conexion = $conexion;
    }

    public function ExecuteSql($sql){
    	$query = sqlsrv_query( $this->_conexion,$sql);
		if($query == false )
		{
			$result = "0"; 
		}
		else
		{
			$result = "1"; 
		}
		return $result;
    }

	public function GetLoad($sql){
		$valores = array();
		$query = sqlsrv_query( $this->_conexion,$sql);
		while ($row = sqlsrv_fetch_object($query)) {
			$valores[] = get_object_vars($row);
		}
		sqlsrv_free_stmt($query);
		// sqlsrv_close($this->_conexion);
		return  $valores;
    }

    
}// END CLASS