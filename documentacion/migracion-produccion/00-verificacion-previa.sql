/*
 * MIGRACION A PRODUCCION - Paso 00
 * Verificacion previa. SOLO LECTURA: no escribe nada en ninguna base.
 *
 * Devuelve varios conjuntos de resultados; guarde la salida completa junto con
 * la evidencia de la ventana (con sqlcmd: -o 00-salida.txt).
 *
 *  1. Servidor, version, edicion, base y permisos del usuario que ejecuta.
 *  2. Ultimo respaldo completo registrado en msdb (lote aparte: si el usuario
 *     no puede leer msdb, solo ese lote falla).
 *  3. Tablas base que los scripts alteran o referencian (EXISTE / FALTA).
 *  4. Columnas base que usan las llaves foraneas y los backfills, con su tipo
 *     y si son PK/unicas.
 *  5. Datos que condicionan el resultado: pagadurias 6 y 8, duplicados de
 *     Terceros.DocumentoTercero.
 *  6. Objetos NUEVOS que ya existen (para saber que se migro antes) y filas
 *     por tabla.
 *  7. Tabla migrations de Laravel.
 *  8. Menu y permisos de Deterioro.
 *  9. Bases externas que la aplicacion lee en tiempo de ejecucion
 *     (Modulos_Faico, UNOEEARAR) y la de pruebas.
 *
 * Que buscar: ver README, seccion "Paso 00: criterios para continuar".
 */

SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET NUMERIC_ROUNDABORT OFF;
GO

SET NOCOUNT ON;

IF DB_NAME() <> N'ArarFinanciera'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera (produccion).', 1;
END

-- 1. Servidor, base y permisos
SELECT seccion = '1. Entorno',
       servidor = @@SERVERNAME,
       base = DB_NAME(),
       version_producto = CAST(SERVERPROPERTY('ProductVersion') AS nvarchar(50)),
       nivel = CAST(SERVERPROPERTY('ProductLevel') AS nvarchar(50)),
       edicion = CAST(SERVERPROPERTY('Edition') AS nvarchar(100)),
       compatibilidad = (SELECT compatibility_level FROM sys.databases WHERE name = DB_NAME()),
       modelo_recuperacion = (SELECT recovery_model_desc FROM sys.databases WHERE name = DB_NAME()),
       intercalacion = CAST(DATABASEPROPERTYEX(DB_NAME(), 'Collation') AS nvarchar(100)),
       idioma_sesion = @@LANGUAGE,
       login = SUSER_SNAME(),
       usuario_bd = USER_NAME(),
       es_db_owner = IS_MEMBER('db_owner'),
       es_db_ddladmin = IS_MEMBER('db_ddladmin'),
       puede_create_table = HAS_PERMS_BY_NAME(NULL, 'DATABASE', 'CREATE TABLE'),
       puede_alter_database_objetos = HAS_PERMS_BY_NAME(NULL, 'DATABASE', 'ALTER');

SELECT seccion = '1. Version completa', version = @@VERSION;
GO

-- 2. Ultimo respaldo completo (msdb)
BEGIN TRY
    SELECT seccion = '2. Ultimo respaldo completo',
           ultimo_full = MAX(b.backup_finish_date),
           horas_desde_ultimo = DATEDIFF(HOUR, MAX(b.backup_finish_date), GETDATE())
    FROM msdb.dbo.backupset b
    WHERE b.database_name = DB_NAME() AND b.type = 'D';
END TRY
BEGIN CATCH
    PRINT 'No se pudo leer msdb.dbo.backupset (' + ERROR_MESSAGE() + '). Confirme el respaldo por otro medio.';
END CATCH
GO

SET NOCOUNT ON;

-- 3. Tablas base que se alteran o se referencian
SELECT seccion = '3. Tablas base',
       t.tabla,
       t.uso,
       estado = CASE WHEN OBJECT_ID(N'dbo.' + t.tabla, N'U') IS NOT NULL THEN 'EXISTE' ELSE 'FALTA' END
FROM (VALUES
    (N'Pagadurias',            N'Paso 18: columnas nuevas y FK desde PagaduriasReglasEdad'),
    (N'Procesos',              N'Paso 19: columnas nuevas; pasos 20 y 21: FK y backfill'),
    (N'TratamientoDatos',      N'Paso 19: columna IdProceso y backfill; paso 20: backfill'),
    (N'Terceros',              N'Paso 19: indice unico; paso 21: FK'),
    (N'DocumentosSolicitados', N'Paso 20: FK y backfill'),
    (N'Menus',                 N'Paso 22'),
    (N'Submenus',              N'Pasos 22 y 23'),
    (N'PermisosRoles',         N'Paso 22'),
    (N'migrations',            N'Paso 24 (si falta, el paso 24 no registra nada)')
) t (tabla, uso)
ORDER BY estado, t.tabla;

-- 4. Columnas base referenciadas por FKs y backfills
SELECT seccion = '4. Columnas base',
       e.tabla,
       e.columna,
       estado = CASE WHEN c.column_id IS NULL THEN 'FALTA' ELSE 'EXISTE' END,
       tipo = ty.name,
       c.max_length,
       c.is_nullable,
       es_pk_o_unica = CASE WHEN EXISTS (
                           SELECT 1 FROM sys.index_columns ic
                           INNER JOIN sys.indexes i ON i.object_id = ic.object_id AND i.index_id = ic.index_id
                           WHERE ic.object_id = c.object_id AND ic.column_id = c.column_id
                             AND (i.is_primary_key = 1 OR i.is_unique = 1)
                             AND (SELECT COUNT(*) FROM sys.index_columns ic2 WHERE ic2.object_id = i.object_id AND ic2.index_id = i.index_id AND ic2.is_included_column = 0) = 1)
                       THEN 1 ELSE 0 END,
       e.nota
FROM (VALUES
    (N'Pagadurias',            N'IdPagaduria',            N'Debe ser bigint y PK (FK de PagaduriasReglasEdad)'),
    (N'Procesos',              N'IdProceso',              N'Debe ser bigint y PK (FKs de ProcesosHistorial, ProcesosDocumentos, ConsultasCentrales)'),
    (N'Procesos',              N'IdTercero',              N'Backfill pasos 19 y 20'),
    (N'Procesos',              N'IdUsuario',              N'Backfill paso 20'),
    (N'Procesos',              N'EstadoProceso',          N'Backfill paso 20'),
    (N'Procesos',              N'FechaCreacion',          N'Backfill pasos 19 y 20'),
    (N'Procesos',              N'updated_at',             N'Backfill paso 20'),
    (N'Procesos',              N'DocumentosCargados',     N'Backfill paso 20'),
    (N'Procesos',              N'DocumentosAprobados',    N'Backfill paso 20'),
    (N'Terceros',              N'IdTercero',              N'Debe ser bigint y PK (FK de ConsultasCentrales)'),
    (N'Terceros',              N'DocumentoTercero',       N'Indice unico paso 19'),
    (N'TratamientoDatos',      N'IdTercero',              N'Backfill paso 19'),
    (N'TratamientoDatos',      N'IdTratamiento',          N'Backfill paso 20'),
    (N'TratamientoDatos',      N'RutaFormato',            N'Backfill paso 20'),
    (N'DocumentosSolicitados', N'IdDocumentoSolicitado',  N'Debe ser bigint y PK (FK de ProcesosDocumentos)'),
    (N'DocumentosSolicitados', N'NombreDocumento',        N'Backfill paso 20'),
    (N'Menus',                 N'IdMenu',                 N'Identity'),
    (N'Menus',                 N'NombreMenu',             N'Paso 22'),
    (N'Menus',                 N'RutaMenu',               N'Paso 22'),
    (N'Menus',                 N'CodigoMenu',             N'Paso 22'),
    (N'Menus',                 N'Orden',                  N'El paso 22 no lo llena si crea el menu'),
    (N'Submenus',              N'IdSubmenu',              N'Identity'),
    (N'Submenus',              N'IdMenu',                 N'Paso 22'),
    (N'Submenus',              N'NombreSubmenu',          N'Paso 22 (30 caracteres)'),
    (N'Submenus',              N'RutaSubmenu',            N'Paso 22 (30 caracteres)'),
    (N'Submenus',              N'CodigoSubmenu',          N'Paso 22'),
    (N'Submenus',              N'EstadoSubmenu',          N'Pasos 22 y 23'),
    (N'PermisosRoles',         N'IdRoles',                N'Paso 22'),
    (N'PermisosRoles',         N'IdSubmenu',              N'Paso 22'),
    (N'migrations',            N'migration',              N'Paso 24'),
    (N'migrations',            N'batch',                  N'Paso 24')
) e (tabla, columna, nota)
LEFT JOIN sys.columns c ON c.object_id = OBJECT_ID(N'dbo.' + e.tabla) AND c.name = e.columna
LEFT JOIN sys.types ty ON ty.user_type_id = c.user_type_id
ORDER BY e.tabla, e.columna;
GO

SET NOCOUNT ON;

-- 5. Datos que condicionan el resultado
IF OBJECT_ID(N'dbo.Pagadurias', N'U') IS NOT NULL
    EXEC(N'SELECT seccion = ''5. Pagadurias'', IdPagaduria, NombrePagaduria,
              marca_paso18 = CASE WHEN IdPagaduria IN (6, 8) THEN ''Recibira UsaReglaSMMLV = 1 si la columna se crea en el paso 18'' ELSE '''' END
         FROM Pagadurias ORDER BY IdPagaduria');

IF OBJECT_ID(N'dbo.Terceros', N'U') IS NOT NULL
    EXEC(N'SELECT seccion = ''5. Terceros duplicados'',
              documentos_repetidos = COUNT(*),
              filas_afectadas = ISNULL(SUM(n), 0),
              efecto = CASE WHEN COUNT(*) > 0 THEN ''El paso 19 NO creara terceros_documentotercero_unique'' ELSE ''Sin duplicados: el indice se creara'' END
         FROM (SELECT DocumentoTercero, n = COUNT(*) FROM Terceros GROUP BY DocumentoTercero HAVING COUNT(*) > 1) d');

IF OBJECT_ID(N'dbo.Procesos', N'U') IS NOT NULL
    EXEC(N'SELECT seccion = ''5. Volumen backfill credito'',
              procesos = (SELECT COUNT(*) FROM Procesos),
              tratamiento_datos = (SELECT COUNT(*) FROM TratamientoDatos),
              terceros = (SELECT COUNT(*) FROM Terceros)');
GO

SET NOCOUNT ON;

-- 6. Objetos nuevos que ya existen y columnas esperadas presentes
DECLARE @esperado TABLE (tabla sysname NOT NULL, columnas nvarchar(max) NOT NULL);

INSERT INTO @esperado (tabla, columnas) VALUES
        (N'det_param_rango_mora', N'id_param,codigo,dias_desde,dias_hasta,etiqueta,pct_deterioro_contable,orden,vigente_desde,vigente_hasta,id_usuario,fecha_registro'),
        (N'det_param_producto', N'id_param,nom_operacion,producto,vigente_desde,vigente_hasta,id_usuario,fecha_registro'),
        (N'det_param_interes', N'id_param,producto,tasa_mora_mensual,aplica_mora,sigue_calculando_suspendido,vigente_desde,vigente_hasta,id_usuario,fecha_registro'),
        (N'det_param_convencion', N'id_param,base_dias,origen_mora,base_incluye_interes,siesa_manda_sobre_base,tarifa_renta,vigente_desde,vigente_hasta,id_usuario,fecha_registro'),
        (N'det_corte', N'id_corte,fecha_corte,fecha_comparacion,estado,id_corte_anterior,id_usuario,fecha_creacion,fecha_ejecucion,duracion_ms,filas_origen,suma_capital_origen,suma_interes_origen,hash_datos,hash_parametros,modo_compatibilidad_excel,observacion,fecha_cierre,id_usuario_cierre,cerrado_con_salvedad,motivo_salvedad,foto_salvedad,fecha_reapertura,id_usuario_reapertura,motivo_reapertura,cuentas_congeladas'),
        (N'det_corte_param_rango_mora', N'id_corte,codigo,dias_desde,dias_hasta,etiqueta,pct_deterioro_contable,orden'),
        (N'det_corte_param_producto', N'id_corte,nom_operacion,producto'),
        (N'det_corte_param_interes', N'id_corte,producto,tasa_mora_mensual,aplica_mora,sigue_calculando_suspendido'),
        (N'det_corte_param_convencion', N'id_corte,base_dias,origen_mora,base_incluye_interes,siesa_manda_sobre_base,tarifa_renta'),
        (N'det_corte_detalle_cuota', N'id_corte,id_operacion,id_cuota,id_detalle_operacion,id_ano,id_periodo,id_cliente,cliente,id_pagador,pagador,id_comisionista,comisionista,fec_operacion,tasa_interes_cliente,saldo_capital,saldo_intereses,saldo_intereses_causado,saldo_mora,saldo_mora_causado,saldo_admon,saldo_neto_recibir,fec_inicial_corriente,fec_final_corriente,fec_inicial_mora,dias_vencidos,dias_corriente,reliquida_mora,tipo_operacion,nom_operacion,nom_mod_operacion,valor_credito,producto,dias_mora_cuota,dias_mora_operacion,calificacion_abc,rango_codigo,capital_corriente,interes_corriente,capital_vencido,interes_vencido,interes_mora,estado_cuota,capital_mes_anterior,duplicada_de'),
        (N'det_deterioro_operacion', N'id_corte,id_operacion,id_cliente,cliente,producto,nom_operacion,fec_operacion,fec_inicial_mora,cuotas,capital_corriente,interes_corriente,capital_vencido,interes_vencido,interes_mora,saldo_admon,dias_mora_operacion,calificacion_abc,rango_codigo,base_deterioro,pct_contable,deterioro_contable,capital_mes_anterior,variacion_capital,saldo_siesa,saldo_topado,fiscal_acumulado_anterior,deterioro_fiscal_individual,deterioro_fiscal_general,deduccion_fiscal_ano,deterioro_fiscal_acumulado,diferencia_temporaria,impuesto_diferido_activo,ano_reversion_fiscal,deterioro_mes_anterior,variacion_deterioro,movimiento,suspendida,id_suspension,interes_vencido_congelado,base_congelada,interes_no_facturado,origen_base,valor_nominal_siesa,origen_saldo_siesa,estado_reversion,interes_prorroga_siesa,capital_vencido_siesa,interes_vencido_siesa'),
        (N'det_corte_cuadre', N'id_corte,codigo,descripcion,valor_detalle,valor_resumen,diferencia,tolerancia,estado,motivo,informativo'),
        (N'det_validacion_excel', N'id_validacion,id_corte,hoja,id_operacion,columna,valor_excel'),
        (N'det_bitacora', N'id_evento,accion,id_corte,id_operacion,valor_anterior,valor_nuevo,id_usuario,doc_usuario,ip,fecha'),
        (N'det_param_fiscal', N'id_param,metodo,pct_anual,dias_minimos_mora,activo,vigente_desde,vigente_hasta,id_usuario,fecha_registro'),
        (N'det_param_fiscal_rango', N'id_param,metodo,rango_codigo,pct,vigente_desde,vigente_hasta,id_usuario,fecha_registro'),
        (N'det_corte_param_fiscal', N'id_corte,metodo,pct_anual,dias_minimos_mora,activo'),
        (N'det_corte_param_fiscal_rango', N'id_corte,metodo,rango_codigo,pct'),
        (N'det_fiscal_acumulado', N'id_operacion,ano_gravable,valor_deducido,id_corte_origen,origen,id_usuario,fecha_registro'),
        (N'det_param_causal_suspension', N'id_param,codigo,descripcion,activa,vigente_desde,vigente_hasta,id_usuario,fecha_registro'),
        (N'det_corte_param_causal_suspension', N'id_corte,codigo,descripcion,activa'),
        (N'det_suspension_interes', N'id_suspension,id_operacion,causal_codigo,fecha_evento,observacion,soporte,origen,existe_en_factoring,interes_congelado,id_corte_congelado,marca_factoring_aplicada,valor_anterior_factoring,fecha_escritura,id_usuario,fecha_registro,fecha_reactivacion,id_usuario_reactivacion,observacion_reactivacion,soporte_nombre,soporte_tipo'),
        (N'det_corte_saldo_siesa', N'id_saldo,id_corte,tipo_docto_cruce,consec_docto_cruce,nit,razon_social,saldo,fecha_extraccion,id_operacion_nota,componente,saldo_vencido'),
        (N'det_conciliacion_partida', N'id_partida,id_corte,id_operacion,numero_operacion,nit,cliente,saldo_siesa,saldo_factoring,diferencia,tipo'),
        (N'det_conciliacion_explicacion', N'id_corte,numero_operacion,estado,explicacion,id_usuario,fecha'),
        (N'det_param_causal_salida', N'id_param,codigo,descripcion,activa,cierra_fiscal,pide_referencia,orden,vigente_desde,vigente_hasta,id_usuario,fecha_registro'),
        (N'det_corte_param_causal_salida', N'id_corte,codigo,descripcion,activa,cierra_fiscal,pide_referencia,orden'),
        (N'det_salida_operacion', N'id_corte,id_operacion,clasificacion,observacion,referencia_operacion_nueva,base_cerrada,deterioro_cerrado,fiscal_acumulado_cerrado,id_usuario,fecha'),
        (N'det_param_cuenta_contable', N'id_param,concepto,producto,cuenta_debito,cuenta_credito,descripcion,vigente_desde,vigente_hasta,id_usuario,fecha_registro'),
        (N'det_corte_param_cuenta_contable', N'id_corte,concepto,producto,cuenta_debito,cuenta_credito,descripcion'),
        (N'Pagadurias', N'EstadoPagaduria,UsaReglaSMMLV,UmbralSMMLV'),
        (N'PagaduriasReglasEdad', N'IdReglaEdad,IdPagaduria,EdadMin,EdadMax,PlazoMaximo,PorcentajeSeguro,created_at,updated_at'),
        (N'ConfiguracionAuditoria', N'Id,Tabla,IdRegistro,Campo,ValorAnterior,ValorNuevo,IdUsuario,Fecha'),
        (N'Procesos', N'EmailTratamientoEnviadoA,FechaEmailTratamiento'),
        (N'TratamientoDatos', N'IdProceso'),
        (N'MotivosRechazo', N'IdMotivo,NombreMotivo,EstadoMotivo'),
        (N'ProcesosHistorial', N'IdHistorial,IdProceso,EstadoAnterior,EstadoNuevo,IdMotivo,Observacion,IdUsuario,Fecha'),
        (N'ProcesosDocumentos', N'IdProcesoDocumento,IdProceso,IdDocumento,Estado,Ruta,NombreArchivo,IdMotivo,Observacion,IdUsuario,Fecha,updated_at'),
        (N'ConsultasCentrales', N'Id,IdProceso,IdTercero,Proveedor,Ambiente,Simulado,FechaConsulta,VigenteHasta,IdUsuario,Exitosa,MensajeError,ResumenJson,RespuestaCruda'),
        (N'ConfiguracionCentrales', N'IdConfiguracion,Clave,Valor,IdUsuario,updated_at');

;WITH col AS (
    SELECT e.tabla, columna = n.c.value('.', 'sysname')
    FROM @esperado e
    CROSS APPLY (SELECT x = CAST(N'<c>' + REPLACE(e.columnas, N',', N'</c><c>') + N'</c>' AS xml)) t
    CROSS APPLY t.x.nodes('/c') n (c)
)
SELECT seccion = '6. Objetos del flujo',
       col.tabla,
       tabla_existe = CASE WHEN OBJECT_ID(N'dbo.' + col.tabla, N'U') IS NOT NULL THEN 'SI' ELSE 'NO' END,
       columnas_esperadas = COUNT(*),
       columnas_presentes = SUM(CASE WHEN COL_LENGTH(N'dbo.' + col.tabla, col.columna) IS NOT NULL THEN 1 ELSE 0 END),
       faltantes = STUFF((SELECT ',' + c2.columna FROM col c2
                          WHERE c2.tabla = col.tabla AND COL_LENGTH(N'dbo.' + c2.tabla, c2.columna) IS NULL
                          FOR XML PATH('')), 1, 1, ''),
       filas = (SELECT SUM(p.rows) FROM sys.partitions p
                WHERE p.object_id = OBJECT_ID(N'dbo.' + col.tabla) AND p.index_id IN (0, 1))
FROM col
GROUP BY col.tabla
ORDER BY tabla_existe DESC, col.tabla;

SELECT seccion = '6. Ancho de det_corte_cuadre.codigo',
       max_length_bytes = c.max_length,
       interpretacion = CASE c.max_length WHEN 20 THEN 'nvarchar(10): el paso 03 la ampliara' WHEN 40 THEN 'nvarchar(20): ya ampliada' ELSE 'Revisar' END
FROM sys.columns c
WHERE c.object_id = OBJECT_ID(N'dbo.det_corte_cuadre') AND c.name = N'codigo';

IF OBJECT_ID(N'dbo.det_corte', N'U') IS NOT NULL
    EXEC(N'SELECT seccion = ''6. Cortes existentes'', id_corte, fecha_corte, estado, fecha_ejecucion FROM det_corte ORDER BY fecha_corte');
GO

SET NOCOUNT ON;

-- 7. Tabla migrations de Laravel
IF OBJECT_ID(N'dbo.migrations', N'U') IS NULL
    SELECT seccion = '7. migrations', estado = 'NO EXISTE: el paso 24 no registrara nada';
ELSE
BEGIN
    EXEC(N'SELECT seccion = ''7. migrations'', total = COUNT(*), ultimo_batch = MAX(batch),
              ultima = (SELECT TOP 1 migration FROM migrations ORDER BY id DESC)
         FROM migrations');
    EXEC(N'SELECT seccion = ''7. migrations 2026 ya registradas'', id, migration, batch
         FROM migrations WHERE migration LIKE ''2026[_]%'' ORDER BY migration');
END
GO

SET NOCOUNT ON;

-- 8. Menu y permisos de Deterioro
IF OBJECT_ID(N'dbo.Submenus', N'U') IS NOT NULL
BEGIN
    SELECT seccion = '8. Menu Deterioro', m.*
    FROM Menus m WHERE m.NombreMenu = N'Deterioro Cartera';

    SELECT seccion = '8. Submenus /deterioro',
           s.IdSubmenu, s.IdMenu, s.NombreSubmenu, s.RutaSubmenu, s.EstadoSubmenu,
           roles = STUFF((SELECT ',' + CAST(p.IdRoles AS varchar(10)) FROM PermisosRoles p
                          WHERE p.IdSubmenu = s.IdSubmenu ORDER BY p.IdRoles FOR XML PATH('')), 1, 1, '')
    FROM Submenus s
    WHERE s.RutaSubmenu LIKE '/deterioro%'
    ORDER BY s.IdSubmenu;
END
GO

SET NOCOUNT ON;

-- 9. Bases externas leidas en tiempo de ejecucion (no las modifica ningun paso)
SELECT seccion = '9. Bases del servidor',
       b.base,
       existe = CASE WHEN DB_ID(b.base) IS NOT NULL THEN 'SI' ELSE 'NO' END,
       b.uso
FROM (VALUES
    (N'Modulos_Faico',          N'Ambiente produccion: origen de la cartera (ResumenVigentesClientes), consulta cross-database del motor de deterioro'),
    (N'Modulos_Faico_prueba',   N'Solo ambiente demo. Produccion NO debe apuntar aqui'),
    (N'UNOEEARAR',              N'SIESA, solo lectura (conexion unoeearar y consultas UNOEEARAR..)'),
    (N'ArarFinanciera_PRUEBAS', N'Solo ambiente demo; se usa en 98-comparar-configuracion-con-pruebas.sql')
) b (base, uso);

SELECT seccion = '9. Origen de cartera en Modulos_Faico',
       ResumenVigentesClientes = CASE WHEN OBJECT_ID(N'Modulos_Faico.dbo.ResumenVigentesClientes') IS NOT NULL THEN 'EXISTE' ELSE 'FALTA' END,
       ResumenVigentesClientes1 = CASE WHEN OBJECT_ID(N'Modulos_Faico.dbo.ResumenVigentesClientes1') IS NOT NULL THEN 'EXISTE' ELSE 'FALTA' END;
GO
