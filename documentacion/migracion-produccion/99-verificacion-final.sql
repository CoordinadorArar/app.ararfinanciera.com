/*
 * MIGRACION A PRODUCCION - Paso 99
 * Verificacion final. SOLO LECTURA: no escribe nada en ninguna base.
 *
 * Devuelve:
 *  1. Un resumen: cuantos OK, FALTA y AVISO.
 *  2. El detalle de cada comprobacion (tipo, objeto, estado, detalle),
 *     primero las FALTA, luego los AVISO y al final los OK.
 *
 * Estados:
 *  OK     el objeto o dato esperado esta.
 *  FALTA  no esta: algun paso no se aplico o fallo. No habilitar la aplicacion.
 *  AVISO  no bloquea, pero requiere decision o tarea posterior (ver README).
 *
 * Comprueba: todas las columnas de las tablas del flujo, el ancho de
 * det_corte_cuadre.codigo, los indices (incluido el filtrado), las llaves
 * foraneas, las semillas, el menu y los permisos, la visibilidad del menu, el
 * registro en migrations y que los textos con tilde no llegaron corruptos por
 * la codificacion del archivo.
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

DECLARE @r TABLE (tipo nvarchar(20) NOT NULL, objeto nvarchar(300) NOT NULL, estado nvarchar(10) NOT NULL, detalle nvarchar(400) NULL);

-- ----------------------------------------------------------------------
-- 1. Columnas
-- ----------------------------------------------------------------------

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

INSERT INTO @r (tipo, objeto, estado, detalle)
SELECT N'COLUMNA',
       c.tabla + N'.' + c.columna,
       CASE WHEN COL_LENGTH(N'dbo.' + c.tabla, c.columna) IS NOT NULL THEN N'OK' ELSE N'FALTA' END,
       CASE WHEN OBJECT_ID(N'dbo.' + c.tabla, N'U') IS NULL THEN N'La tabla no existe' END
FROM (
    SELECT e.tabla, columna = n.c.value('.', 'sysname')
    FROM @esperado e
    CROSS APPLY (SELECT x = CAST(N'<c>' + REPLACE(e.columnas, N',', N'</c><c>') + N'</c>' AS xml)) t
    CROSS APPLY t.x.nodes('/c') n (c)
) c;

INSERT INTO @r (tipo, objeto, estado, detalle)
SELECT N'COLUMNA', N'det_corte_cuadre.codigo = nvarchar(20)',
       CASE WHEN ISNULL((SELECT max_length FROM sys.columns WHERE object_id = OBJECT_ID(N'dbo.det_corte_cuadre') AND name = N'codigo'), 0) = 40 THEN N'OK' ELSE N'FALTA' END,
       N'max_length en bytes: ' + ISNULL(CAST((SELECT max_length FROM sys.columns WHERE object_id = OBJECT_ID(N'dbo.det_corte_cuadre') AND name = N'codigo') AS nvarchar(10)), N'-');

-- ----------------------------------------------------------------------
-- 2. Indices y llaves primarias con nombre
-- ----------------------------------------------------------------------

INSERT INTO @r (tipo, objeto, estado, detalle)
SELECT N'INDICE',
       i.tabla + N'.' + i.indice,
       CASE WHEN x.index_id IS NOT NULL AND (i.filtrado = 0 OR x.has_filter = 1) THEN N'OK' ELSE N'FALTA' END,
       CASE WHEN i.filtrado = 1 THEN N'Filtro: ' + ISNULL(x.filter_definition, N'-') END
FROM (VALUES
    (N'det_param_rango_mora',              N'ix_param_rango_vigencia', 0),
    (N'det_param_producto',                N'ix_param_producto_vigencia', 0),
    (N'det_param_interes',                 N'ix_param_interes_vigencia', 0),
    (N'det_param_convencion',              N'ix_param_convencion_vigencia', 0),
    (N'det_corte',                         N'ux_det_corte_fecha', 0),
    (N'det_corte_param_rango_mora',        N'pk_det_corte_param_rango', 0),
    (N'det_corte_param_producto',          N'pk_det_corte_param_producto', 0),
    (N'det_corte_param_interes',           N'pk_det_corte_param_interes', 0),
    (N'det_corte_param_convencion',        N'det_corte_param_convencion_id_corte_primary', 0),
    (N'det_corte_detalle_cuota',           N'pk_det_corte_detalle_cuota', 0),
    (N'det_corte_detalle_cuota',           N'ix_det_detalle_cliente', 0),
    (N'det_deterioro_operacion',           N'pk_det_deterioro_operacion', 0),
    (N'det_deterioro_operacion',           N'ix_det_deterioro_prod_rango', 0),
    (N'det_corte_cuadre',                  N'pk_det_corte_cuadre', 0),
    (N'det_validacion_excel',              N'ix_det_validacion', 0),
    (N'det_bitacora',                      N'ix_det_bitacora_corte', 0),
    (N'det_param_fiscal',                  N'ix_param_fiscal_vigencia', 0),
    (N'det_param_fiscal_rango',            N'ix_param_fiscal_rango_vigencia', 0),
    (N'det_corte_param_fiscal',            N'pk_det_corte_param_fiscal', 0),
    (N'det_corte_param_fiscal_rango',      N'pk_det_corte_param_fiscal_rango', 0),
    (N'det_fiscal_acumulado',              N'pk_det_fiscal_acumulado', 0),
    (N'det_param_causal_suspension',       N'ix_param_causal_susp_vigencia', 0),
    (N'det_corte_param_causal_suspension', N'pk_det_corte_param_causal_susp', 0),
    (N'det_suspension_interes',            N'ix_det_suspension_operacion', 0),
    (N'det_suspension_interes',            N'ux_det_suspension_operacion_activa', 1),
    (N'det_corte_saldo_siesa',             N'ix_det_saldo_siesa_corte', 0),
    (N'det_corte_saldo_siesa',             N'ix_det_saldo_siesa_cruce', 0),
    (N'det_corte_saldo_siesa',             N'ix_det_saldo_siesa_nota', 0),
    (N'det_conciliacion_partida',          N'ix_det_concilia_corte', 0),
    (N'det_conciliacion_partida',          N'ix_det_concilia_operacion', 0),
    (N'det_conciliacion_explicacion',      N'pk_det_concilia_explicacion', 0),
    (N'det_param_causal_salida',           N'ix_param_causal_salida_vigencia', 0),
    (N'det_corte_param_causal_salida',     N'pk_det_corte_param_causal_salida', 0),
    (N'det_salida_operacion',              N'pk_det_salida_operacion', 0),
    (N'det_param_cuenta_contable',         N'ix_param_cuenta_contable_vigencia', 0),
    (N'det_corte_param_cuenta_contable',   N'ux_det_corte_param_cuenta_contable', 0),
    (N'ConfiguracionAuditoria',            N'configuracionauditoria_tabla_idregistro_index', 0),
    (N'ProcesosHistorial',                 N'procesoshistorial_idproceso_index', 0),
    (N'ProcesosDocumentos',                N'procesosdocumentos_idproceso_iddocumento_unique', 0),
    (N'ConsultasCentrales',                N'consultascentrales_idtercero_proveedor_fechaconsulta_index', 0),
    (N'ConsultasCentrales',                N'consultascentrales_idproceso_index', 0),
    (N'ConfiguracionCentrales',            N'configuracioncentrales_clave_unique', 0)
) i (tabla, indice, filtrado)
LEFT JOIN sys.indexes x ON x.object_id = OBJECT_ID(N'dbo.' + i.tabla) AND x.name = i.indice;

-- ----------------------------------------------------------------------
-- 3. Llaves foraneas (solo credito: las tablas det_* no tienen FKs)
-- ----------------------------------------------------------------------

INSERT INTO @r (tipo, objeto, estado, detalle)
SELECT N'FK',
       f.tabla + N'.' + f.fk,
       CASE WHEN fk.object_id IS NOT NULL AND OBJECT_NAME(fk.referenced_object_id) = f.referencia THEN N'OK' ELSE N'FALTA' END,
       N'-> ' + f.referencia + CASE WHEN fk.is_disabled = 1 OR fk.is_not_trusted = 1 THEN N' (deshabilitada o no confiable)' ELSE N'' END
FROM (VALUES
    (N'PagaduriasReglasEdad', N'pagaduriasreglasedad_idpagaduria_foreign', N'Pagadurias'),
    (N'ProcesosHistorial',    N'procesoshistorial_idproceso_foreign',      N'Procesos'),
    (N'ProcesosHistorial',    N'procesoshistorial_idmotivo_foreign',       N'MotivosRechazo'),
    (N'ProcesosDocumentos',   N'procesosdocumentos_idproceso_foreign',     N'Procesos'),
    (N'ProcesosDocumentos',   N'procesosdocumentos_iddocumento_foreign',   N'DocumentosSolicitados'),
    (N'ProcesosDocumentos',   N'procesosdocumentos_idmotivo_foreign',      N'MotivosRechazo'),
    (N'ConsultasCentrales',   N'consultascentrales_idproceso_foreign',     N'Procesos'),
    (N'ConsultasCentrales',   N'consultascentrales_idtercero_foreign',     N'Terceros')
) f (tabla, fk, referencia)
LEFT JOIN sys.foreign_keys fk ON fk.name = f.fk AND fk.parent_object_id = OBJECT_ID(N'dbo.' + f.tabla);

-- ----------------------------------------------------------------------
-- 4. Semillas, configuracion, menu, migrations y codificacion
--    Cada comprobacion corre aparte (sp_executesql) para que una tabla o
--    columna ausente se informe como FALTA en vez de abortar el script.
--    @ok: 1 = OK, 0 = FALTA, 2 = AVISO.
-- ----------------------------------------------------------------------

DECLARE @c TABLE (orden int IDENTITY, tipo nvarchar(20) NOT NULL, objeto nvarchar(300) NOT NULL, consulta nvarchar(max) NOT NULL);

INSERT INTO @c (tipo, objeto, consulta) VALUES
(N'SEMILLA', N'det_param_rango_mora: rangos A-F',
 N'SELECT @n = COUNT(DISTINCT codigo) FROM det_param_rango_mora WHERE codigo IN (N''A'',N''B'',N''C'',N''D'',N''E'',N''F'') AND vigente_hasta IS NULL; SET @ok = CASE WHEN @n = 6 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' de 6 rangos vigentes'';'),
(N'SEMILLA', N'det_param_producto: 5 NomOperacion',
 N'SELECT @n = COUNT(DISTINCT nom_operacion) FROM det_param_producto WHERE nom_operacion IN (N''FINANCIACION'',N''LETRA DE CAMBIO'',N''LIBRANZAS'',N''DESCUENTO DE CHEQUES'',N''FACTORING'') AND vigente_hasta IS NULL; SET @ok = CASE WHEN @n = 5 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' de 5'';'),
(N'SEMILLA', N'det_param_interes: 3 productos',
 N'SELECT @n = COUNT(DISTINCT producto) FROM det_param_interes WHERE producto IN (N''FINANCIACION'',N''LIBRANZAS'',N''FACTORING'') AND vigente_hasta IS NULL; SET @ok = CASE WHEN @n = 3 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' de 3'';'),
(N'SEMILLA', N'det_param_convencion: vigente',
 N'SELECT @n = COUNT(*) FROM det_param_convencion WHERE vigente_hasta IS NULL; SET @ok = CASE WHEN @n >= 1 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' fila(s) vigente(s)'';'),
(N'SEMILLA', N'det_param_fiscal: INDIVIDUAL y GENERAL',
 N'SELECT @n = COUNT(DISTINCT metodo) FROM det_param_fiscal WHERE metodo IN (N''INDIVIDUAL'',N''GENERAL''); SET @ok = CASE WHEN @n = 2 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' de 2'';'),
(N'SEMILLA', N'det_param_fiscal_rango: GENERAL C-F',
 N'SELECT @n = COUNT(DISTINCT rango_codigo) FROM det_param_fiscal_rango WHERE metodo = N''GENERAL'' AND rango_codigo IN (N''C'',N''D'',N''E'',N''F''); SET @ok = CASE WHEN @n = 4 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' de 4'';'),
(N'SEMILLA', N'det_param_causal_suspension: 5 causales',
 N'SELECT @n = COUNT(DISTINCT codigo) FROM det_param_causal_suspension WHERE codigo IN (N''FALLECIMIENTO'',N''INSOLVENCIA'',N''COBRO_JURIDICO'',N''FIN_CUOTAS'',N''SIN_DETERMINAR''); SET @ok = CASE WHEN @n = 5 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' de 5'';'),
(N'SEMILLA', N'det_param_causal_suspension: SIN_DETERMINAR inactiva',
 N'SELECT @n = COUNT(*) FROM det_param_causal_suspension WHERE codigo = N''SIN_DETERMINAR'' AND activa = 0; SET @ok = CASE WHEN @n = 1 THEN 1 ELSE 0 END; SET @det = NULL;'),
(N'SEMILLA', N'det_param_causal_salida: 4 causales',
 N'SELECT @n = COUNT(DISTINCT codigo) FROM det_param_causal_salida WHERE codigo IN (N''RECAUDO_TOTAL'',N''CASTIGO'',N''CIERRE_Y_APERTURA'',N''OTRA''); SET @ok = CASE WHEN @n = 4 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' de 4'';'),
(N'SEMILLA', N'det_param_cuenta_contable: cuentas del asiento',
 N'SELECT @n = COUNT(*) FROM det_param_cuenta_contable; SET @ok = CASE WHEN @n > 0 THEN 1 ELSE 2 END; SET @det = CASE WHEN @n = 0 THEN N''Vacia por diseno: el exportable del asiento se rechaza hasta que Contabilidad defina las cuentas'' ELSE CAST(@n AS nvarchar(10)) + N'' cuentas'' END;'),
(N'DATO', N'det_fiscal_acumulado: 1399 historico (EXCEL_1399)',
 N'SELECT @n = COUNT(*) FROM det_fiscal_acumulado WHERE origen = N''EXCEL_1399''; SET @ok = CASE WHEN @n > 0 THEN 1 ELSE 2 END; SET @det = CASE WHEN @n = 0 THEN N''Sin cargar: cargar el 1399 antes de calcular el primer corte (README)'' ELSE CAST(@n AS nvarchar(10)) + N'' filas'' END;'),
(N'SEMILLA', N'MotivosRechazo: 1 a 6',
 N'SELECT @n = COUNT(DISTINCT IdMotivo) FROM MotivosRechazo WHERE IdMotivo BETWEEN 1 AND 6; SET @ok = CASE WHEN @n = 6 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' de 6'';'),
(N'SEMILLA', N'ConfiguracionCentrales: 4 claves',
 N'SELECT @n = COUNT(DISTINCT Clave) FROM ConfiguracionCentrales WHERE Clave IN (N''predeterminado'',N''vigenciaDias'',N''habilitado.transunion'',N''habilitado.datacredito''); SET @ok = CASE WHEN @n = 4 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' de 4'';'),
(N'SEMILLA', N'PagaduriasReglasEdad: toda pagaduria con reglas',
 N'SELECT @n = COUNT(*) FROM Pagadurias p WHERE NOT EXISTS (SELECT 1 FROM PagaduriasReglasEdad r WHERE r.IdPagaduria = p.IdPagaduria); SET @ok = CASE WHEN @n = 0 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' pagadurias sin reglas'';'),
(N'DATO', N'Pagadurias con UsaReglaSMMLV = 1',
 N'SELECT @det = STUFF((SELECT N'','' + CAST(IdPagaduria AS nvarchar(20)) + N'' '' + CAST(NombrePagaduria AS nvarchar(60)) FROM Pagadurias WHERE UsaReglaSMMLV = 1 ORDER BY IdPagaduria FOR XML PATH('''')), 1, 1, N''''); SET @ok = 2;'),
(N'BACKFILL', N'ProcesosHistorial: todo proceso con estado tiene historial',
 N'SELECT @n = COUNT(*) FROM Procesos p WHERE p.EstadoProceso IS NOT NULL AND NOT EXISTS (SELECT 1 FROM ProcesosHistorial h WHERE h.IdProceso = p.IdProceso); SET @ok = CASE WHEN @n = 0 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' procesos sin historial'';'),
(N'BACKFILL', N'TratamientoDatos.IdProceso: asignado si el tercero tiene procesos',
 N'SELECT @n = COUNT(*) FROM TratamientoDatos td WHERE td.IdProceso IS NULL AND EXISTS (SELECT 1 FROM Procesos p WHERE p.IdTercero = td.IdTercero); SET @ok = CASE WHEN @n = 0 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' filas sin asignar'';'),
(N'INDICE', N'Terceros.terceros_documentotercero_unique',
 N'DECLARE @dup int = (SELECT COUNT(*) FROM (SELECT DocumentoTercero FROM Terceros GROUP BY DocumentoTercero HAVING COUNT(*) > 1) d); SET @ok = CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE name = N''terceros_documentotercero_unique'' AND object_id = OBJECT_ID(N''dbo.Terceros'')) THEN 1 WHEN @dup > 0 THEN 2 ELSE 0 END; SET @det = CASE WHEN @ok = 2 THEN CAST(@dup AS nvarchar(10)) + N'' documentos duplicados: depurar y repetir el paso 19'' END;'),
(N'MENU', N'Submenus: 18 rutas de deterioro',
 N'SELECT @n = COUNT(DISTINCT RutaSubmenu) FROM Submenus WHERE RutaSubmenu IN (N''/deterioro-cortes'',N''/deterioro-resumen'',N''/deterioro-detalle-operaciones'',N''/deterioro-contable-fiscal'',N''/deterioro-evolucion'',N''/deterioro-suspensiones'',N''/deterioro-conciliacion'',N''/deterioro-controles'',N''/deterioro-accion-consultar'',N''/deterioro-accion-calcular'',N''/deterioro-accion-suspender'',N''/deterioro-accion-conciliar'',N''/deterioro-accion-clasificar'',N''/deterioro-accion-cerrar'',N''/deterioro-accion-forzarCierre'',N''/deterioro-accion-reabrir'',N''/deterioro-accion-auditar'',N''/deterioro-accion-exportar''); SET @ok = CASE WHEN @n = 18 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' de 18'';'),
(N'MENU', N'PermisosRoles: 52 permisos de deterioro (roles 1, 2, 7)',
 N'SELECT @n = COUNT(*) FROM (VALUES (N''/deterioro-cortes'',1),(N''/deterioro-cortes'',2),(N''/deterioro-cortes'',7),(N''/deterioro-resumen'',1),(N''/deterioro-resumen'',2),(N''/deterioro-resumen'',7),(N''/deterioro-detalle-operaciones'',1),(N''/deterioro-detalle-operaciones'',2),(N''/deterioro-detalle-operaciones'',7),(N''/deterioro-contable-fiscal'',1),(N''/deterioro-contable-fiscal'',2),(N''/deterioro-contable-fiscal'',7),(N''/deterioro-evolucion'',1),(N''/deterioro-evolucion'',2),(N''/deterioro-evolucion'',7),(N''/deterioro-suspensiones'',1),(N''/deterioro-suspensiones'',2),(N''/deterioro-suspensiones'',7),(N''/deterioro-conciliacion'',1),(N''/deterioro-conciliacion'',2),(N''/deterioro-conciliacion'',7),(N''/deterioro-controles'',1),(N''/deterioro-controles'',2),(N''/deterioro-controles'',7),(N''/deterioro-accion-consultar'',1),(N''/deterioro-accion-consultar'',2),(N''/deterioro-accion-consultar'',7),(N''/deterioro-accion-calcular'',1),(N''/deterioro-accion-calcular'',2),(N''/deterioro-accion-calcular'',7),(N''/deterioro-accion-suspender'',1),(N''/deterioro-accion-suspender'',2),(N''/deterioro-accion-suspender'',7),(N''/deterioro-accion-conciliar'',1),(N''/deterioro-accion-conciliar'',2),(N''/deterioro-accion-conciliar'',7),(N''/deterioro-accion-clasificar'',1),(N''/deterioro-accion-clasificar'',2),(N''/deterioro-accion-clasificar'',7),(N''/deterioro-accion-cerrar'',1),(N''/deterioro-accion-cerrar'',2),(N''/deterioro-accion-cerrar'',7),(N''/deterioro-accion-forzarCierre'',1),(N''/deterioro-accion-forzarCierre'',2),(N''/deterioro-accion-reabrir'',1),(N''/deterioro-accion-reabrir'',2),(N''/deterioro-accion-auditar'',1),(N''/deterioro-accion-auditar'',2),(N''/deterioro-accion-auditar'',7),(N''/deterioro-accion-exportar'',1),(N''/deterioro-accion-exportar'',2),(N''/deterioro-accion-exportar'',7)) v (ruta, rol) WHERE EXISTS (SELECT 1 FROM PermisosRoles p INNER JOIN Submenus s ON s.IdSubmenu = p.IdSubmenu WHERE s.RutaSubmenu = v.ruta AND p.IdRoles = v.rol); SET @ok = CASE WHEN @n = 52 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' de 52'';'),
(N'MENU', N'Visibilidad: solo /deterioro-cortes dibujado',
 N'SELECT @n = COUNT(*) FROM Submenus WHERE RutaSubmenu LIKE ''/deterioro%'' AND EstadoSubmenu = 1; SET @ok = CASE WHEN @n = 1 AND EXISTS (SELECT 1 FROM Submenus WHERE RutaSubmenu = N''/deterioro-cortes'' AND EstadoSubmenu = 1) THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' entradas visibles'';'),
(N'MIGRATIONS', N'migrations: 19 migraciones de 2026 registradas',
 N'IF OBJECT_ID(N''dbo.migrations'', N''U'') IS NULL BEGIN SET @ok = 2; SET @det = N''La tabla migrations no existe: el paso 24 no registro nada (README, seccion 12, punto 4)''; RETURN; END; SELECT @n = COUNT(DISTINCT migration) FROM migrations WHERE migration IN (N''2026_08_31_100000_create_det_parametros_tables'',N''2026_08_31_100100_create_det_corte_tables'',N''2026_08_31_100200_create_det_resultado_tables'',N''2026_09_01_100000_create_det_fiscal_tables'',N''2026_09_02_100000_create_det_diferido_tables'',N''2026_09_03_100000_create_det_historico_tables'',N''2026_09_03_110000_create_det_suspension_tables'',N''2026_09_04_100000_create_det_siesa_tables'',N''2026_09_05_100000_create_det_cierre_tables'',N''2026_09_14_100000_create_det_cuenta_contable_tables'',N''2026_09_15_100000_add_soporte_adjunto_to_det_suspension'',N''2026_09_16_100000_add_duplicada_de_to_det_corte_detalle_cuota'',N''2026_09_18_100000_add_atribucion_nota_a_det_siesa'',N''2026_09_23_100000_add_prorroga_a_det_deterioro'',N''2026_09_28_100000_add_vencido_siesa_a_det_deterioro'',N''2026_10_07_100000_credito_fase1_pagadurias'',N''2026_10_07_110000_credito_fase2_registro'',N''2026_10_07_120000_credito_fase3_procesos'',N''2026_10_07_130000_credito_fase4_centrales''); SET @ok = CASE WHEN @n = 19 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' de 19'';'),
(N'CODIFICACION', N'Tildes: MotivosRechazo 2',
 N'SELECT @n = COUNT(*) FROM MotivosRechazo WHERE IdMotivo = 2 AND NombreMotivo = N''Mal h'' + NCHAR(225) + N''bito de pago''; SET @ok = CASE WHEN @n = 1 THEN 1 ELSE 0 END; SET @det = (SELECT NombreMotivo FROM MotivosRechazo WHERE IdMotivo = 2);'),
(N'CODIFICACION', N'Tildes: causal INSOLVENCIA',
 N'SELECT @n = COUNT(*) FROM det_param_causal_suspension WHERE codigo = N''INSOLVENCIA'' AND descripcion = N''Admisi'' + NCHAR(243) + N''n del deudor a un proceso de insolvencia''; SET @ok = CASE WHEN @n = 1 THEN 1 ELSE 0 END; SET @det = (SELECT TOP 1 descripcion FROM det_param_causal_suspension WHERE codigo = N''INSOLVENCIA'');'),
(N'CODIFICACION', N'Tildes: causal FIN_CUOTAS',
 N'SELECT @n = COUNT(*) FROM det_param_causal_suspension WHERE codigo = N''FIN_CUOTAS'' AND descripcion LIKE N''Finalizaci'' + NCHAR(243) + N''n%''; SET @ok = CASE WHEN @n = 1 THEN 1 ELSE 0 END; SET @det = (SELECT TOP 1 descripcion FROM det_param_causal_suspension WHERE codigo = N''FIN_CUOTAS'');'),
(N'CODIFICACION', N'Tildes: causal de salida RECAUDO_TOTAL',
 N'SELECT @n = COUNT(*) FROM det_param_causal_salida WHERE codigo = N''RECAUDO_TOTAL'' AND descripcion = N''Recaudo total de la operaci'' + NCHAR(243) + N''n''; SET @ok = CASE WHEN @n = 1 THEN 1 ELSE 0 END; SET @det = (SELECT TOP 1 descripcion FROM det_param_causal_salida WHERE codigo = N''RECAUDO_TOTAL'');'),
(N'CODIFICACION', N'Tildes: submenu /deterioro-accion-auditar',
 N'SELECT @n = COUNT(*) FROM Submenus WHERE RutaSubmenu = N''/deterioro-accion-auditar'' AND NombreSubmenu = N''Consultar bit'' + NCHAR(225) + N''cora''; SET @ok = CASE WHEN @n >= 1 THEN 1 ELSE 0 END; SET @det = (SELECT TOP 1 NombreSubmenu FROM Submenus WHERE RutaSubmenu = N''/deterioro-accion-auditar'');'),
(N'CODIFICACION', N'Sin mojibake en textos sembrados',
 N'SELECT @n = (SELECT COUNT(*) FROM MotivosRechazo WHERE NombreMotivo LIKE N''%'' + NCHAR(195) + N''%'') + (SELECT COUNT(*) FROM det_param_causal_suspension WHERE descripcion LIKE N''%'' + NCHAR(195) + N''%'') + (SELECT COUNT(*) FROM det_param_causal_salida WHERE descripcion LIKE N''%'' + NCHAR(195) + N''%'') + (SELECT COUNT(*) FROM Submenus WHERE RutaSubmenu LIKE ''/deterioro%'' AND NombreSubmenu LIKE N''%'' + NCHAR(195) + N''%''); SET @ok = CASE WHEN @n = 0 THEN 1 ELSE 0 END; SET @det = CAST(@n AS nvarchar(10)) + N'' textos con caracteres corruptos'';');

DECLARE @i int = 1, @max int = (SELECT MAX(orden) FROM @c);
DECLARE @tipo nvarchar(20), @objeto nvarchar(300), @sql nvarchar(max), @ok int, @n int, @det nvarchar(400);

WHILE @i <= @max
BEGIN
    SELECT @tipo = tipo, @objeto = objeto, @sql = consulta FROM @c WHERE orden = @i;
    SELECT @ok = NULL, @n = NULL, @det = NULL;

    BEGIN TRY
        EXEC sp_executesql @sql, N'@ok int OUTPUT, @n int OUTPUT, @det nvarchar(400) OUTPUT', @ok = @ok OUTPUT, @n = @n OUTPUT, @det = @det OUTPUT;
    END TRY
    BEGIN CATCH
        SELECT @ok = 0, @det = LEFT(N'Error al comprobar: ' + ERROR_MESSAGE(), 400);
    END CATCH

    INSERT INTO @r (tipo, objeto, estado, detalle)
    VALUES (@tipo, @objeto, CASE @ok WHEN 1 THEN N'OK' WHEN 2 THEN N'AVISO' ELSE N'FALTA' END, @det);

    SET @i += 1;
END

-- ----------------------------------------------------------------------
-- Resultado
-- ----------------------------------------------------------------------

SELECT resumen = N'Verificacion final ' + DB_NAME(),
       ok = SUM(CASE WHEN estado = N'OK' THEN 1 ELSE 0 END),
       falta = SUM(CASE WHEN estado = N'FALTA' THEN 1 ELSE 0 END),
       aviso = SUM(CASE WHEN estado = N'AVISO' THEN 1 ELSE 0 END),
       resultado = CASE WHEN SUM(CASE WHEN estado = N'FALTA' THEN 1 ELSE 0 END) = 0 THEN N'MIGRACION COMPLETA' ELSE N'HAY FALTANTES: NO HABILITAR LA APLICACION' END
FROM @r;

SELECT tipo, objeto, estado, detalle
FROM @r
ORDER BY CASE estado WHEN N'FALTA' THEN 0 WHEN N'AVISO' THEN 1 ELSE 2 END, tipo, objeto;
GO
