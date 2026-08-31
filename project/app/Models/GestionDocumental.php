<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GestionDocumental extends Model
{
    use HasFactory;
    public $timestamps = false;

    /**Buscar folios (ids) a partir del documento o nombre del cliente.
     * Nota: los clientes archivados en gde_folio_cliente son terceros de SIESA (UNOEEARAR),
     * no coinciden con la tabla local Terceros (usada solo para el flujo de solicitud de crédito). */
    public static function buscarFoliosPorCliente($valor)
    {
        // Nota: el driver ODBC de SQL Server no soporta bien un mismo parámetro con nombre
        // repetido varias veces en la consulta (da error "COUNT field incorrect or syntax error"),
        // por eso aquí se usan placeholders posicionales (?) con el valor repetido en el array.
        $sql = "SELECT DISTINCT fc.idfolio
                FROM gde_folio_cliente fc
                INNER JOIN UNOEEARAR.dbo.t200_mm_terceros t ON t.f200_nit = fc.cedula AND t.f200_id_cia = 7
                WHERE fc.cedula = ?
                   OR t.f200_razon_social LIKE ?
                   OR t.f200_nombres LIKE ?
                   OR t.f200_apellido1 LIKE ?
                   OR t.f200_apellido2 LIKE ?
                ORDER BY fc.idfolio DESC";
        $like = '%'.$valor.'%';
        return DB::select($sql, [$valor, $like, $like, $like, $like]);
    }

    /**Buscar folios (ids) a partir del código de folio */
    public static function buscarFoliosPorFolio($valor)
    {
        $sql = "SELECT id AS idfolio FROM gde_folio WHERE folio LIKE :folio ORDER BY id DESC";
        return DB::select($sql, ['folio' => '%'.$valor.'%']);
    }

    /**Buscar folios (ids) a partir del número de factura */
    public static function buscarFoliosPorFactura($valor)
    {
        $sql = "SELECT DISTINCT idfolio FROM gde_folio_factura WHERE factura LIKE :factura ORDER BY idfolio DESC";
        return DB::select($sql, ['factura' => '%'.$valor.'%']);
    }

    /**Información completa de un folio: datos del folio, clientes asociados, facturas y su documento escaneado */
    public static function detalleFolio($idFolio)
    {
        $folio = DB::select("SELECT * FROM gde_folio WHERE id = :id", ['id' => $idFolio]);

        $clientes = DB::select(
            "SELECT fc.id, fc.cedula, fc.empresa, fc.fecha, fc.hora,
                    u.nombreUsuario AS UsuarioRegistro,
                    t.f200_nit AS DocumentoTercero,
                    COALESCE(
                        NULLIF(LTRIM(RTRIM(t.f200_razon_social)),''),
                        NULLIF(LTRIM(RTRIM(CONCAT(t.f200_nombres,' ',t.f200_apellido1,' ',t.f200_apellido2))),'')
                    ) AS NombresTercero,
                    '' AS ApellidosTercero,
                    ct.f015_telefono AS TelefonoTercero,
                    ct.f015_email AS EmailTercero,
                    ct.f015_direccion1 AS DireccionDomicilioTercero
             FROM gde_folio_cliente fc
             LEFT JOIN UNOEEARAR.dbo.t200_mm_terceros t ON t.f200_nit = fc.cedula AND t.f200_id_cia = 7
             LEFT JOIN UNOEEARAR.dbo.t201_mm_clientes cl ON cl.f201_rowid_tercero = t.f200_rowid AND cl.f201_id_sucursal = '001'
             LEFT JOIN UNOEEARAR.dbo.t015_mm_contactos ct ON ct.f015_rowid = cl.f201_rowid_contacto
             LEFT JOIN users u ON u.IdUsuario = fc.usuario
             WHERE fc.idfolio = :id",
            ['id' => $idFolio]
        );

        $facturas = DB::select(
            "SELECT ff.id, ff.factura, ff.fecha, ff.hora,
                    u.nombreUsuario AS UsuarioRegistro
             FROM gde_folio_factura ff
             LEFT JOIN users u ON u.IdUsuario = ff.usuario
             WHERE ff.idfolio = :id
             ORDER BY ff.fecha DESC",
            ['id' => $idFolio]
        );

        /**Buscar el documento escaneado (gde_bdr) asociado a cada factura del folio */
        foreach ($facturas as $factura) {
            $documento = DB::select(
                "SELECT TOP 1 id, img, fecha, hora, iddoc
                 FROM gde_bdr WHERE factura = :factura ORDER BY id DESC",
                ['factura' => $factura->factura]
            );
            $factura->documento = $documento[0] ?? null;

            // Los documentos ya alojados en este servidor (project/folios/) quedaron bloqueados a
            // acceso directo; se reescriben para servirse por la ruta autenticada. Los alojados en
            // el servidor externo documental.disrayco.com no se tocan (no está bajo nuestro control).
            if ($factura->documento && str_contains((string) $factura->documento->img, '/project/folios/')) {
                $factura->documento->img = route('ver-documento-folio', ['idFolio' => $idFolio]);
            }
        }

        return [
            'folio' => $folio[0] ?? null,
            'clientes' => $clientes,
            'facturas' => $facturas,
            'ubicacion' => self::ubicacionFolio($idFolio),
        ];
    }

    /**Ubicación física de la caja donde está archivado el folio (piso, pasillo, estante, posición, columna, fila).
     * Nota: gde_folio_caja.caja referencia gd_caja_nueva.id (no gd_caja, que es la tabla antigua sin relación). */
    public static function ubicacionFolio($idFolio)
    {
        $sql = "SELECT c.id AS idCaja, c.nombre AS NombreCaja,
                       u.id_piso, u.id_pasillo, u.id_estante, u.id_posicion, u.id_columna, u.id_fila
                FROM gde_folio_caja fc
                INNER JOIN gd_caja_nueva c ON c.id = fc.caja
                LEFT JOIN gd_ubicacion_nueva u ON u.id_caja = c.id
                WHERE fc.idfolio = :id";
        $ubicacion = DB::select($sql, ['id' => $idFolio]);
        return $ubicacion[0] ?? null;
    }

    /* =====================  Digitalización de facturas  ===================== */

    /**Facturas (operaciones) pendientes de digitalizar: todo lo que hay en vOperacionesDigitalizar
     * que todavía no tenga un registro en gde_folio_factura */
    public static function facturasPendientesDigitalizar()
    {
        $sql = "SELECT v.*
                FROM vOperacionesDigitalizar v
                WHERE NOT EXISTS (
                    SELECT 1 FROM gde_folio_factura ff WHERE ff.factura = CAST(v.IdOperacion AS varchar(30))
                )
                ORDER BY v.FecOperacion DESC";
        return DB::select($sql);
    }

    /**Estadística de facturas pendientes de digitalizar, agrupadas por asesor */
    public static function pendientesDigitalizarPorAsesor()
    {
        $sql = "SELECT v.Asesor, COUNT(*) AS total
                FROM vOperacionesDigitalizar v
                WHERE NOT EXISTS (
                    SELECT 1 FROM gde_folio_factura ff WHERE ff.factura = CAST(v.IdOperacion AS varchar(30))
                )
                GROUP BY v.Asesor
                ORDER BY total DESC";
        return DB::select($sql);
    }

    /**Estadística de facturas pendientes de digitalizar, agrupadas por mes de la operación */
    public static function pendientesDigitalizarPorMes()
    {
        $sql = "SELECT FORMAT(v.FecOperacion, 'yyyy-MM') AS mes, COUNT(*) AS total
                FROM vOperacionesDigitalizar v
                WHERE NOT EXISTS (
                    SELECT 1 FROM gde_folio_factura ff WHERE ff.factura = CAST(v.IdOperacion AS varchar(30))
                )
                GROUP BY FORMAT(v.FecOperacion, 'yyyy-MM')
                ORDER BY mes";
        return DB::select($sql);
    }

    /**Crear una caja nueva (estado=0 En proceso, ubicado=0) */
    public static function crearCaja($nombre)
    {
        return DB::table('gd_caja_nueva')->insertGetId([
            'nombre' => $nombre,
            'estado' => 0,
            'ubicado' => 0,
        ], 'id');
    }

    /**Digitalizar una factura: crea el folio y sus relaciones (factura, cliente, caja).
     * Devuelve el id del folio creado; el llamador debe guardar el archivo y registrar gde_bdr aparte. */
    public static function digitalizarFactura($factura, $idCliente, $idCaja, $usuario)
    {
        return DB::transaction(function () use ($factura, $idCliente, $idCaja, $usuario) {
            $fecha = date('Y-m-d');
            $hora = date('H:i:s');

            $idFolio = DB::table('gde_folio')->insertGetId(['folio' => null], 'id');
            DB::table('gde_folio')->where('id', $idFolio)->update(['folio' => 'EMP'.$idFolio]);

            DB::table('gde_folio_factura')->insert([
                'idfolio' => $idFolio,
                'factura' => $factura,
                'fecha' => $fecha,
                'hora' => $hora,
                'usuario' => $usuario,
            ]);

            DB::table('gde_folio_cliente')->insert([
                'idfolio' => $idFolio,
                'cedula' => $idCliente,
                'empresa' => 1,
                'fecha' => $fecha,
                'hora' => $hora,
                'usuario' => $usuario,
            ]);

            DB::table('gde_folio_caja')->insert([
                'idfolio' => $idFolio,
                'caja' => $idCaja,
                'fecha' => $fecha,
                'hora' => $hora,
                'usuario' => $usuario,
            ]);

            return $idFolio;
        });
    }

    /**Registrar el documento escaneado (URL del PDF) de una factura ya digitalizada */
    public static function registrarDocumentoEscaneado($idFolio, $factura, $urlDocumento, $usuario)
    {
        return DB::table('gde_bdr')->insert([
            'idfolio' => (string) $idFolio,
            'factura' => $factura,
            'img' => $urlDocumento,
            'fecha' => date('Y-m-d'),
            'hora' => date('H:i:s'),
            'usuario' => $usuario,
        ]);
    }

    /**Cajas pendientes de ubicar (estado=0), para el selector del formulario de digitalización.
     * Una caja en estado=0 sigue disponible para recibir más facturas hasta que se le asigne ubicación física. */
    public static function cajasEnProceso()
    {
        $sql = "SELECT c.id, c.nombre, COUNT(fc.id) AS totalFacturas
                FROM gd_caja_nueva c
                LEFT JOIN gde_folio_caja fc ON fc.caja = c.id
                WHERE c.estado = 0
                GROUP BY c.id, c.nombre
                ORDER BY c.id DESC";
        return DB::select($sql);
    }

    /**Cajas pendientes de ubicar (estado=0), con el total de facturas de cada una */
    public static function cajasPendientesUbicacion()
    {
        $sql = "SELECT c.id, c.nombre, COUNT(fc.id) AS totalFacturas
                FROM gd_caja_nueva c
                LEFT JOIN gde_folio_caja fc ON fc.caja = c.id
                WHERE c.estado = 0
                GROUP BY c.id, c.nombre
                ORDER BY c.id DESC";
        return DB::select($sql);
    }

    /**Asignar la ubicación física final de una caja: pasa a "Ubicada" */
    public static function ubicarCaja($idCaja, $piso, $pasillo, $estante, $posicion, $columna, $fila)
    {
        $datos = [
            'id_piso' => $piso,
            'id_pasillo' => $pasillo,
            'id_estante' => $estante,
            'id_posicion' => $posicion,
            'id_columna' => $columna,
            'id_fila' => $fila,
        ];

        $existe = DB::table('gd_ubicacion_nueva')->where('id_caja', $idCaja)->first();
        if ($existe) {
            DB::table('gd_ubicacion_nueva')->where('id_caja', $idCaja)->update($datos);
        } else {
            $datos['id_caja'] = $idCaja;
            DB::table('gd_ubicacion_nueva')->insert($datos);
        }

        return DB::table('gd_caja_nueva')->where('id', $idCaja)->update(['estado' => 1, 'ubicado' => 1]);
    }
}
