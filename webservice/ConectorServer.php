<?php 
require_once("SqlServer/Manager.php");
date_default_timezone_set('America/Bogota');
setlocale(LC_TIME,"es_CO.UTF-8");

Class ConectorServer {

    private $DB;
    
    function __construct() {
        $this->DB=new DB_Manager();
    }
    
    public function RegistrarUsuario(){
        $sql = "INSERT INTO ArarFinancieraCreditos.dbo.Amenu(NombreMenu,Descripcion,Logo,Orden) VALUES ('Menu','Descripcion','Logo','1')";

        $control =  $this->DB->ExecuteSql($sql);
        if (!empty($control)){
            return $control;
        }
        else{              
            return 0;
        }
    }

    public function SelectMenu(){
        $sql = "SELECT * FROM ArarFinanciera.dbo.Amenu";

        $control =  $this->DB->GetLoad($sql);
        if (!empty($control)){
            return $control;
        }
        else{              
            return 0;
        }
    }

    public function loadUsers($users){

        $sql = "SELECT *,ISNULL(RutaImagen,1) AS ruta,u.DocUsuario
         FROM ArarFinanciera.dbo.usuario u 
         LEFT JOIN ArarFinanciera.dbo.Aperfil_imagen i ON u.DocUsuario = i.DocUsuario 
         INNER JOIN ArarFinanciera.dbo.Aperfil_usuario r ON r.IdUsuario = u.IdUsuario 
         INNER JOIN ArarFinanciera.dbo.Aperfil p ON p.IdPerfil = r.IdPerfil
         WHERE Usuario='$users' AND EstadoUsuario ='1'";

        $control =  $this->DB->GetLoad($sql);
        if (!empty($control)){
            return $control;
        }
        else{              
            return 0;
        }
    }

    public function loadFunciones($idPerfil){
        $sql = "SELECT *, f.ruta
         FROM ArarFinanciera.dbo.Apermisos_perfil p
         INNER JOIN ArarFinanciera.dbo.Afunciones f ON  f.IdFunciones = p.IdFuncion
         INNER JOIN ArarFinanciera.dbo.Amenu m  ON m.IdMenu = f.IdMenu
         WHERE IdPerfil ='$idPerfil'
         ORDER BY m.Orden  ASC";

        $control=  $this->DB->GetLoad($sql);
        if (!empty($control)){
            return $control;
        }
        else{              
            return 0;
        }
    }

    public function PerfilUsers($for){
        $sql = "SELECT IdPerfil 
        FROM ArarFinanciera.dbo.Aperfil_usuario
        INNER JOIN ArarFinanciera.dbo.usuario ON Aperfil_usuario.IdUsuario = usuario.IdUsuario
        WHERE DocUsuario='$for'";

        $control=  $this->DB->GetLoad($sql);
        if (!empty($control)){
            return $control[0];
        }
        else{
            return 0;
        }
    }

    public function LoadMenu($DocUsuario){
        $sql = "SELECT 
        NombreMenu ,MAX(Logo) AS logo 
        ,Amenu.Orden 
         FROM ArarFinanciera.dbo.Afunciones 
         INNER JOIN ArarFinanciera.dbo.Amenu ON Afunciones.IdMenu = Amenu.IdMenu
         INNER JOIN ArarFinanciera.dbo.Apermisos_perfil ON Apermisos_perfil.IdFuncion = Afunciones.IdFunciones 
         INNER JOIN ArarFinanciera.dbo.Aperfil ON Aperfil.IdPerfil = Apermisos_perfil.IdPerfil 
         INNER JOIN ArarFinanciera.dbo.Aperfil_usuario ON Aperfil_usuario.IdPerfil = Aperfil.IdPerfil
         INNER JOIN ArarFinanciera.dbo.usuario ON usuario.IdUsuario = Aperfil_usuario.IdUsuario 
         WHERE DocUsuario='$DocUsuario' AND EstadoUsuario='1'
         GROUP BY NombreMenu, Orden 
         ORDER BY Orden ASC";

        $control=  $this->DB->GetLoad($sql);

        if (!empty($control)){
            return $control;
        }
        else{
            return $sql;
        }
    }

    public function LoadFuncionesMenu($DocUsuario,$Menu){
        $sql = "SELECT Funcion,a.Ruta AS Ruta,a.Descripcion
         FROM ArarFinanciera.dbo.Afunciones a
         INNER JOIN ArarFinanciera.dbo.Amenu b ON  b.IdMenu = a.IdMenu 
         INNER JOIN ArarFinanciera.dbo.Apermisos_perfil c ON c.IdFuncion = a.IdFunciones
         INNER JOIN ArarFinanciera.dbo.Aperfil d ON d.IdPerfil = c.IdPerfil
         INNER JOIN ArarFinanciera.dbo.Aperfil_usuario e ON e.IdPerfil = d.IdPerfil
         INNER JOIN ArarFinanciera.dbo.usuario f ON f.IdUsuario = e.IdUsuario
         WHERE DocUsuario = '$DocUsuario' AND EstadoUsuario = '1' AND NombreMenu = '$Menu' AND visible = '1'";

        $control=  $this->DB->GetLoad($sql);

        if (!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function Search_Usuario($documento){
        $sql = 'SELECT  ';
        $sql .= ' * ';
        $sql .= ' FROM ArarFinanciera.dbo.Usuario ';
        $sql .= " WHERE DocUsuario='$documento' ";
        $control=  $this->DB->GetLoad($sql);
        if (!empty($control)){
            return $control[0];
        }
        else{
            return 0;
        }
    }

    public function UpdatePerfil($nombres,$apellidos,$email,$contrasena,$doc_usuario){
        if($contrasena != ''){
            $sql = "UPDATE ArarFinanciera.dbo.Usuario 
            SET Nombres = '$nombres', Apellidos = '$apellidos',Email = '$email',Contrasena = '$contrasena' 
            WHERE DocUsuario = '$doc_usuario' ";
        }
        else{
            $sql = "UPDATE ArarFinanciera.dbo.Usuario 
            SET Nombres = '$nombres', Apellidos = '$apellidos',Email = '$email'
            WHERE DocUsuario = '$doc_usuario' ";
        }

        $control= $this->DB->ExecuteSql($sql);
        if($control != 0){
            return 1;
        }
        else{
            return 0;
        }
    }

    public function ValidacionPerfilImagen($doc_usuario){ 
        $sql = "SELECT * FROM ArarFinanciera.dbo.Aperfil_imagen WHERE DocUsuario = '$doc_usuario' ";

        $control= $this->DB->GetLoad($sql);
        
        if(!empty($control)){
            return $control[0];
        }
        else{
            return 0;
        }
    }

    public function InsertPerfilImagen($RutaImagen,$DocUsuario){
        $sql = "INSERT INTO ArarFinanciera.dbo.Aperfil_imagen(RutaImagen,DocUsuario) VALUES('$RutaImagen','$DocUsuario')";

        $control= $this->DB->ExecuteSql($sql);
        if($control != 0){
            return 1;
        }
        else{
            return 0;
        }
    }

    public function UpdatePerfilImagen($RutaImagen,$DocUsuario){
        $sql = "UPDATE ArarFinanciera.dbo.Aperfil_imagen SET RutaImagen = '$RutaImagen' WHERE DocUsuario = '$DocUsuario'";

        $control= $this->DB->ExecuteSql($sql);
        if($control != 0){
            return 1;
        }
        else{
            return 0;
        }
    }

    public function TasaLibranza(){
        $sql = "SELECT * FROM TasaLibranza ORDER BY anio ASC,mes ASC";

        $control= $this->DB->GetLoad($sql);
        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function ValidarTasa($anio,$mes){
        $sql = "SELECT * FROM TasaLibranza 
         WHERE anio = '$anio' AND mes = '$mes' ";

        $control= $this->DB->GetLoad($sql);
        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function InsertTasa($tasaEA,$tasaNMV,$mes,$anio){
        $sql = "INSERT INTO TasaLibranza (tasa_ea,tasa_nmv,mes,anio) VALUES ('$tasaEA','$tasaNMV','$mes','$anio');";

        $control= $this->DB->ExecuteSql($sql);
        if($control != 0){
            return 1;
        }
        else{
            return 0;
        }
    }

    public function LoadUSuarios(){
        $sql = "SELECT * 
        FROM ArarFinanciera.dbo.Usuario a
        LEFT JOIN ArarFinanciera.dbo.Aperfil_usuario b ON a.IdUsuario = b.IdUsuario
        LEFT JOIN ArarFinanciera.dbo.Aperfil c ON b.IdPerfil = c.IdPerfil";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function LoadPerfil(){
        $sql = "SELECT * 
        FROM ArarFinanciera.dbo.Aperfil";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function LoadComisionistas(){
        $sql = "SELECT * FROM FactoringManagerDatos..Comisionistas";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }
    
    public function LoadEstadoCivil(){
        $sql = "SELECT * FROM FactoringManagerDatos..EstadoCivil";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function LoadGenero(){
        $sql = "SELECT * FROM FactoringManagerDatos..Genero";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function LoadTipoVivienda(){
        $sql = "SELECT * FROM FactoringManagerDatos..TiposVivienda";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function LoadCiudades(){
        $sql = "SELECT * FROM FactoringManagerDatos..CiudadesAct";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function LoadTipIdentificacion(){
        $sql = "SELECT * FROM FactoringManagerDatos..TiposIdentificacion";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function LoadTipContribuyente(){
        $sql = "SELECT * FROM FactoringManagerDatos..TiposContribuyente";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function LoadSectorIndustrial(){
        $sql = "SELECT * FROM FactoringManagerDatos..SectoresIndustriales";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function LoadClientes($idcliente){
        if($idcliente != ''){
            $sql = "SELECT IdCliente,replace(NomCliente,'ñ','n') as NomCliente,replace(ApeCliente,'ñ','N') as ApeCliente ,DirOficinaCliente AS Direccion,
            IdPais = '169',substring(CodDaneCiudad,1,2) As IdDepartamento,substring(CodDaneCiudad,3,5) As IdCiudad,
            TelOficinaCliente AS Telefono,TelMovilCliente AS Celular,EmailCliente As Email,FecNacimiento AS FechaNacimiento
            FROM FactoringManagerDatos..Clientes a 
            JOIN FactoringManagerDatos..CiudadesAct b ON a.IdCiudadOficinaCliente = b.IdCiudad
            WHERE IdCliente = '$idcliente'";
        }
        else{
            $sql = "SELECT * FROM FactoringManagerDatos..Clientes";
        }

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function AddUsuario($usuario,$nombres,$apellidos,$contrasena,$email,$docusuario,$telefono,$region){
        $sql = "INSERT INTO ArarFinanciera.dbo.Usuario(Usuario,Nombres,Apellidos,Contrasena,Email,DocUsuario,EstadoUsuario,Telefono,Region) 
        VALUES('$usuario','$nombres','$apellidos','$contrasena','$email','$docusuario','1','$telefono','$region') ";

        $control= $this->DB->ExecuteSql($sql);
        if($control != 0){
            return 1;
        }
        else{
            return 0;
        }
    }

    public function LoadIdUsuario($usuario){
        $sql = "SELECT IdUsuario FROM ArarFinanciera.dbo.Usuario WHERE Usuario = '$usuario'";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control[0];
        }
        else{
            return 0;
        }
    }

    public function AddPerfil($idusuario,$idperfil){
        $sql = "INSERT INTO ArarFinanciera.dbo.Aperfil_usuario(IdUsuario,IdPerfil) VALUES ('$idusuario','$idperfil')";

        $control= $this->DB->ExecuteSql($sql);
        if($control != 0){
            return 1;
        }
        else{
            return 0;
        }
    }


    public function LoadFuncion(){
        $sql = "SELECT a.IdFunciones,Funcion,NombreMenu,a.Descripcion
         FROM ArarFinanciera.dbo.Afunciones a
         INNER JOIN ArarFinanciera.dbo.Amenu b ON b.IdMenu = a.IdMenu";
        $control= $this->DB->GetLoad($sql);
        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function LoadFuncionPermisos($idperfil){
        $sql = "SELECT * FROM ArarFinanciera.dbo.Apermisos_perfil WHERE IdPerfil = '$idperfil'";

        $control= $this->DB->GetLoad($sql);
        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function DeletePermisosFuncion($idperfil,$idfuncion){
        $sql = "DELETE FROM ArarFinanciera.dbo.Apermisos_perfil WHERE  IdPerfil = '$idperfil' AND IdFuncion = '$idfuncion'";

        $control= $this->DB->ExecuteSql($sql);
        if($control != 0){
            return 1;
        }
        else{
            return 0;
        }
    }

    public function AddPermisosFuncion($idperfil,$idfuncion){
        $sql = "INSERT INTO ArarFinanciera.dbo.Apermisos_perfil (IdPerfil,IdFuncion) VALUES ('$idperfil','$idfuncion')";

        $control= $this->DB->ExecuteSql($sql);
        if($control != 0){
            return 1;
        }
        else{
            return 0;
        }
    }

    public function LoadDtoUsuario($idusuario){
        $sql = "SELECT *,b.IdPerfil As idperfil 
         FROM ArarFinanciera.dbo.Usuario a 
         INNER JOIN ArarFinanciera.dbo.Aperfil_usuario b ON b.IdUsuario = a.IdUsuario
         INNER JOIN ArarFinanciera.dbo.Aperfil c ON c.IdPerfil = b.IdPerfil
         WHERE a.IdUsuario = '$idusuario'";

        $control= $this->DB->GetLoad($sql);
        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function UpdateUsuario($idusuario,$usuario,$nombres,$apellidos,$email,$docusuario,$telefono,$region,$contrasena){
        if($contrasena == ''){
            $sql = "UPDATE ArarFinanciera.dbo.Usuario 
             SET Usuario = '$usuario',Nombres = '$nombres', Apellidos = '$apellidos',Email = '$email',DocUsuario = '$docusuario',
             Telefono = '$telefono',Region = '$region'
             WHERE IdUsuario = '$idusuario'";
        }
        else{
            $sql = "UPDATE ArarFinanciera.dbo.Usuario 
             SET Usuario = '$usuario',Nombres = '$nombres', Apellidos = '$apellidos',Email = '$email',DocUsuario = '$docusuario',
             Telefono = '$telefono',Region = '$region' ,Contrasena = '".md5($contrasena)."'
             WHERE IdUsuario = '$idusuario'";
        }

        $control= $this->DB->ExecuteSql($sql);
        if($control != 0){
            return 1;
        }
        else{
            return 0;
        }
    }

    public function ValidarPerfil($nomperfil){
        $sql = "SELECT * FROM ArarFinanciera.dbo.Aperfil WHERE NombrePerfil = '".ucfirst($nomperfil)."'";

        $control= $this->DB->GetLoad($sql);
        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function AddPerfilUsuario($nomperfil){
        $sql = "INSERT INTO ArarFinanciera.dbo.Aperfil (NombrePerfil,EstadoPerfil,UrlPerfil) 
         VALUES ('".ucfirst($nomperfil)."','1','".strtolower($nomperfil)."')";

        $control= $this->DB->ExecuteSql($sql);
        if($control != 0){
            $carpeta = "../".strtolower($nomperfil).'/';
            if (!file_exists($carpeta)) {
                mkdir(strtolower($carpeta), 0777, true);
                //fopen($carpeta."/dashboard.php","u=rwaxc,g-rwaxc");
                $fichero = $carpeta."/dashboard.php";
                $actual = '<div style="min-height: 500px"><H4 class="text-center">Bienvenido</H4></div><?php include_once("footer.php") ?>';
                file_put_contents($fichero, $actual);
                chmod($fichero, 0777);
                return 1;
            }

        }
        else{
            return 0;
        }
    }

    public function UpdatePerfilUsuario($perfil,$idusuario){
        $sql = "UPDATE ArarFinanciera.dbo.Aperfil_usuario SET IdPerfil = '$perfil' 
         WHERE IdUsuario = '$idusuario' ";

       $control= $this->DB->ExecuteSql($sql);
       if($control != 0){
            return 1;
       }
       else{
           return 0;
       }
    }

    public function ValidarUsuarioSiesa($fecha){
        //BD MODULO PRUEBAS
        /*$sql = "SELECT c.IdCliente,isnull(a.f200_id,0) as IdSiesa,c.NomCliente,c.ApeCliente,isnull(b.f201_rowid_tercero,0) as cliente
         FROM FactoringManagerDatos..Clientes c
         LEFT JOIN  UNOEEARAR..t200_mm_terceros a 
         ON a.f200_id = 
         CASE WHEN CHARINDEX('-',IdCliente) > 0 THEN LEFT(c.IdCliente,CHARINDEX('-',c.IdCliente)-1) ELSE c.IdCliente END 
         COLLATE Modern_Spanish_CI_AS AND a.f200_id_cia = 7
         LEFT JOIN UNOEEARAR..t201_mm_clientes b ON a.f200_rowid = b.f201_rowid_tercero
         WHERE FecModifica >= '20220101'
         GROUP BY c.IdCliente,a.f200_id,a.f200_id_cia,FecModifica,c.NomCliente,c.ApeCliente,b.f201_rowid_tercero
         ORDER BY FecModifica ASC ";*/

        //BD MODULO REAL
        $sql = "SELECT c.IdCliente,isnull(a.f200_id,0) as IdSiesa,c.NomCliente,c.ApeCliente,isnull(b.f201_rowid_tercero,0) as cliente
        ,isnull(d.f202_rowid_tercero,0) as Idproveedortercero
        FROM FactoringManagerDatos..Clientes c
        LEFT JOIN UNOEEARAR..t200_mm_terceros a 
        ON a.f200_id = CASE WHEN CHARINDEX('-',IdCliente) > 0 THEN LEFT(c.IdCliente,CHARINDEX('-',c.IdCliente)-1) ELSE c.IdCliente END 
        COLLATE Modern_Spanish_CI_AS AND a.f200_id_cia = 7
        LEFT JOIN UNOEEARAR..t201_mm_clientes b ON a.f200_rowid = b.f201_rowid_tercero
        LEFT JOIN UNOEEARAR..t202_mm_proveedores d ON a.f200_rowid = d.f202_rowid_tercero
        WHERE FecModifica >= '20220101'
        GROUP BY c.IdCliente,a.f200_id,a.f200_id_cia,FecModifica,c.NomCliente,c.ApeCliente,b.f201_rowid_tercero,d.f202_rowid_tercero
        ORDER BY FecModifica ASC
        ";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }


    // Cupones Bancarios 
    public function getAllBancos(){
        $sql = "SELECT * FROM Bancos";

        $control= $this->DB->GetLoad($sql);

       if(!empty($control)){
           return $control;
       }
       else{
           return 0;
       }
    }
    
    // get All Operaciones
    public function getOperacionById($id_operacion){
        $sql = " SELECT o.*
                , cli.NomCliente
                , cli.ApeCliente
                , cli.DirOficinaCliente
                , cli.TelDomicilioCliente
                , cli.TelMovilCliente 
                FROM Operaciones o
                INNER JOIN Clientes cli ON cli.IdCliente = o.IdCliente
                WHERE o.IdOperacion = '$id_operacion' ";

        $control= $this->DB->GetLoad($sql);

       if(!empty($control)){
           return $control;
       }
       else{
           return 0;
       }
    }

    public function getSearchReliquidacion($id_reliquidacion){
        $sql = "SELECT cns.IdOperacion, cns.IDCLiente, cns.Cliente, rtc.FecInicialCorriente, rtc.FecFinalCorriente, rtc.DiasCorriente, rtc.ReliquidaCapital 
                FROM DetReliquidaClientes rtc 
                INNER JOIN cnsFiltroDetOpeRelClientes cns ON rtc.IdDetalleOperacion = cns.IdDetalleOperacion
                WHERE rtc.IdDocumento = '$id_reliquidacion'  
                AND ReliquidaCapital > 0 ";

        $control= $this->DB->GetLoad($sql);

       if(!empty($control)){
           return $control;
       }
       else{
           return 0;
       }
    }

    public function addCabeceraCupones($usuario){
        $sql = "INSERT INTO dbo.Cupones(UsuarioRegistro) VALUES ('$usuario')";

        $control =  $this->DB->ExecuteSql($sql);
        if (!empty($control)){
            $sql = " SELECT TOP 1 * FROM Cupones ORDER BY IdCupon DESC ";
            $control= $this->DB->GetLoad($sql);
            if(!empty($control)){
                return $control[0]['IdCupon'];
            }
            else{ return 0; }
        }
        else{ return 0; }
    }
    
    public function addDetalleCupones($id_cupon, $consecutivo, $id_operacion, $id_cliente, $valor_cupon, $fecha_limite_cupon, $usuario_registro){
        $sql = "INSERT INTO dbo.CuponesDetalles(Consecutivo, IdCupon, IdOperacion, IdCliente, ValorCupon, FechaLimiteCupon, UsuarioRegistro) 
                VALUES ('$consecutivo', '$id_cupon', '$id_operacion', '$id_cliente', '$valor_cupon', '$fecha_limite_cupon', '$usuario_registro');";

        $control =  $this->DB->ExecuteSql($sql);
        if (!empty($control)){
            return $control;
        }
        else{ 
            return 0; 
        }
    }
    
    public function deleteCabeceraAndDetalleCupones($id_cupon){
        $sql = " DELETE FROM CuponesDetalles WHERE IdCupon = '$id_cupon' ";
        $control =  $this->DB->ExecuteSql($sql);
        
        if (!empty($control)){
            
            $sql = " DELETE FROM Cupones WHERE IdCupon = '$id_cupon' ";
            $control =  $this->DB->ExecuteSql($sql);
            if (!empty($control)){
                return $control;
            }
            else{ 
                return 0; 
            }
        }
        else{ 
            return 0; 
        }
    }

     // get All Operaciones By IdCupon
    public function getAllDetailCuponByIdCupon($id_cupon){
        $sql = " SELECT * FROM CuponesDetalles WHERE IdCupon = '$id_cupon' ";
        $control= $this->DB->GetLoad($sql);

       if(!empty($control)){
           return $control;
       }
       else{
           return 0;
       }
    }

    // get All Operaciones By IdOperacion
    public function getAllOperacionesByIdOperacion($id_operacion){
        $sql = " SELECT cd.*, c.NomCliente, c.ApeCliente FROM CuponesDetalles cd
                INNER JOIN Clientes c ON c.IdCliente = cd.IdCliente
                WHERE cd.IdOperacion = '$id_operacion' ";
        $control= $this->DB->GetLoad($sql);

       if(!empty($control)){
           return $control;
       }
       else{
           return 0;
       }
    }

    // get All Cupones 
    public function getAllCuponesGenerados(){
        $sql = " SELECT cd.*, c.NomCliente, c.ApeCliente, NombreUsuarioRegistro = CONCAT(u.Nombres,' ',u.Apellidos)  FROM CuponesDetalles cd
                INNER JOIN Clientes c ON c.IdCliente = cd.IdCliente
                LEFT JOIN Usuario u ON u.DocUsuario = cd.UsuarioRegistro
                ORDER BY cd.IdCupon, Consecutivo ASC  ";
        $control= $this->DB->GetLoad($sql);

       if(!empty($control)){
           return $control;
       }
       else{
           return 0;
       }
    }

}

Class ConectorServer1 {

    private $DB;
    
    function __construct() {
        $this->DB=new DB_Manager1();
    }

    public function ValidaFactoringSiesa($fechainicial,$fechafinal,$tipodocumento){
        $sql = "SELECT distinct factoring=a.idfactura, siesa=b.f350_consec_docto,a.tipoDocumento
         FROM BD_ARAR.dbo.[v_facturas_factoring] a
         LEFT JOIN UNOEEARAR..t350_co_docto_contable b  ON a.idfactura = b.f350_consec_docto 
         and  b.f350_id_tipo_docto =a.tipoDocumento COLLATE Modern_Spanish_CI_AS
         and b.f350_id_cia=7
         WHERE a.tipoDocumento IN ('$tipodocumento') and a.fecdocumento >= '$fechainicial' and a.fecdocumento <= '$fechafinal'
         GROUP BY a.idfactura, b.f350_consec_docto,a.tipoDocumento
         HAVING b.f350_consec_docto is  null
         ORDER BY factoring  ASC";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }

    }

    public function FactoringFactura($id,$tipodocumento){
        $sql = "SELECT tipoDocumento, idfactura,   fecdocumento, idtercero,   Nota = REPLACE(Nota, 'ñ' , 'N'),  cuenta,       SUM(debito) AS Debito,sum(credito) as credito, centrocosto,tipo,
        sum(base) as Base,fechaVencimiento
        FROM BD_ARAR.dbo.[v_facturas_factoring] WHERE idfactura in ($id) AND tipoDocumento IN ('$tipodocumento')
        group by tipoDocumento,    idfactura,   fecdocumento, idtercero,   Nota,  cuenta, centrocosto,tipo,fechaVencimiento
        order by tipoDocumento,idfactura ASC";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function DatosOperaciones($fechainicial,$fechafinal){
    
        $sql = "SELECT distinct tipdocumento=a.TipoDocumento, a.IdOperacion,siesa=b.idoperacion  
        FROM BD_ARAR.dbo.V_Operaciones_Factoring a
        LEFT JOIN BD_ARAR..v_operaciones_siesa b  ON a.TipoDocumento = b.tipo COLLATE Modern_Spanish_CI_AS
        and  a.IdOperacion=b.idoperacion   
        WHERE a.TipoDocumento = 'ope' and FecOperacion>='$fechainicial'"; 
        if($fechafinal != ''){
            $sql .= " and FecOperacion<='$fechafinal'";
        }
        $sql .= " group by a.TipoDocumento, a.IdOperacion,b.idoperacion  
         having b.idoperacion is null";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }


    public function ReclasificarOperaciones($operacion){
        $sql = "SELECT distinct tipdocumento=a.TipoDocumento, a.IdOperacion,siesa=''  
        FROM BD_ARAR.dbo.V_Operaciones_Factoring a
        WHERE a.TipoDocumento = 'ope' and a.IdOperacion='$operacion'"; 
        
        $sql .= " group by a.TipoDocumento, a.IdOperacion ";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }
    public function BusquedaOperacionId($idOperacion){
        $sql = "SELECT TipoDocumento,IdOperacion,Cuota,IdCliente,FecOperacion,FecVencimiento,cuenta,Debito,Credito,Tipo,vendedor,Nota = REPLACE(Nota, 'ñ' , 'N'),FecModifica        
         FROM BD_ARAR.dbo.V_Operaciones_Factoring a
         WHERE IdOperacion IN ($idOperacion)";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    public function BusquedaOperacionReclasificar($idOperacion){
        $sql = "SELECT TipoDocumento,IdOperacion,Cuota,IdCliente,FecOperacion,FecVencimiento,cuenta,Debito,Credito,Tipo,vendedor,Nota = REPLACE(Nota, 'ñ' , 'N'),FecModifica        
         FROM BD_ARAR.dbo.v_operaciones_factoring_reclasificar a
        WHERE IdOperacion IN ($idOperacion)";

        $control= $this->DB->GetLoad($sql);

        if(!empty($control)){
            return $control;
        }
        else{
            return 0;
        }
    }

    
    public function ValidarTerceros($idcliente){
        /*$sql ="SELECT * FROM BD_ARAR.dbo.v_terceros_factoring
        WHERE f200_nit = '$idcliente'";*/

        $sql = "SELECT * FROM UNOEEARAR.dbo.t200_mm_terceros 
         WHERE f200_id_cia = '7' and f200_nit = '$idcliente' ";

        $control = $this->DB->GetLoad($sql);
        if (!empty($control)){
            return $control;
        }
        else{              
            return 0;
        }
    }

    public function ValidarCliente($idcliente){
        /*$sql ="SELECT * FROM BD_ARAR.dbo.v_clientes_siesa 
        WHERE Nit = '$idcliente'";*/

        $sql = "select f200_id_cia  as Compañia ,f200_nit as Nit,f200_razon_social as Nombre,f200_apellido1,f200_apellido2,f200_nombres,b.f200_id_tipo_ident
         ,f015_email as mail,f015_celular as Celular,f015_telefono as Telefono,f015_direccion1 as Direccion,f015_id_pais as Pais,f015_id_depto as Dpto,f015_id_ciudad as Ciudad
         ,f201_id_sucursal,f201_id_vendedor,f201_id_cond_pago,f201_id_moneda,f015_rowid,a.f201_id_tipo_cli
         from UNOEEARAR.dbo.t200_mm_terceros b
         join UNOEEARAR.dbo.t201_mm_clientes a on b.f200_rowid=a.f201_rowid_tercero 
         and f201_id_sucursal='001'
         join UNOEEARAR.dbo.t015_mm_contactos d on  f015_rowid=f201_rowid_contacto
         WHERE f200_nit = '$idcliente' and f200_id_cia = '7' ";

        $control = $this->DB->GetLoad($sql);
        if (!empty($control)){
            return $control;
        }
        else{              
            return 0;
        }
    }

    public function ValidarProveedor($idcliente){
        /*$sql = "SELECT * 
         FROM BD_ARAR.dbo.v_proveedor_siesa
         WHERE f200_nit = '$idcliente'";*/
        $sql = "SELECT * 
         FROM UNOEEARAR.dbo.t200_mm_terceros a
         JOIN UNOEEARAR.dbo.t202_mm_proveedores b ON a.f200_rowid = b.f202_rowid_tercero and f202_id_cia = 7
         WHERE f200_nit = '$idcliente'";

        $control = $this->DB->GetLoad($sql);
        if (!empty($control)){
        return $control;
        }
        else{              
        return $sql;
        }
    }


}

?>