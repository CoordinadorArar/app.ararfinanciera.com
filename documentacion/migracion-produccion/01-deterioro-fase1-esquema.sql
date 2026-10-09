/*
 * MIGRACION A PRODUCCION - Paso 01
 * Deterioro de Cartera - Fase 1 (parametricas, corte, snapshot, resultados,
 * cuadres, staging de validacion y bitacora).
 *
 * GENERADO para esta migracion: no existia un DDL previo de la fase 1. Equivale
 * a `php artisan migrate` de:
 *   2026_08_31_100000_create_det_parametros_tables.php
 *   2026_08_31_100100_create_det_corte_tables.php
 *   2026_08_31_100200_create_det_resultado_tables.php
 * El DDL reproduce el texto que emite Illuminate\Database\Schema\Grammars\
 * SqlServerGrammar (minusculas, corchetes, Increment > Nullable > Default).
 *
 * ESTADO ESPERADO EN PRODUCCION: segun deterioro-fase1-validacion.md las 14
 * tablas de esta fase ya se crearon directamente en ArarFinanciera. En ese caso
 * este paso no hace nada (cada tabla esta guardada por OBJECT_ID). Existe para
 * que el flujo sea completo y reproducible sobre una base sin la fase 1.
 *
 * Crea solo las columnas ORIGINALES de la fase 1: las de fases posteriores las
 * agregan los pasos 03 en adelante. det_corte_cuadre.codigo nace en
 * nvarchar(10); el paso 03 la amplia a nvarchar(20).
 *
 * Prerrequisitos: paso 00 revisado. Ninguna tabla det_* tiene llaves foraneas.
 *
 * Idempotente y transaccional.
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
SET XACT_ABORT ON;

IF DB_NAME() <> N'ArarFinanciera'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera (produccion).', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    -- ------------------------------------------------------------------
    -- 1. Parametricas (2026_08_31_100000_create_det_parametros_tables)
    -- ------------------------------------------------------------------

    IF OBJECT_ID(N'dbo.det_param_rango_mora', N'U') IS NULL
    BEGIN
        create table [det_param_rango_mora] ([id_param] int identity primary key not null, [codigo] nchar(1) not null, [dias_desde] int not null, [dias_hasta] int not null, [etiqueta] nvarchar(40) not null, [pct_deterioro_contable] decimal(7, 4) not null, [orden] tinyint not null, [vigente_desde] date not null, [vigente_hasta] date null, [id_usuario] int not null, [fecha_registro] datetime not null);
        create index [ix_param_rango_vigencia] on [det_param_rango_mora] ([vigente_desde], [vigente_hasta]);
    END

    IF OBJECT_ID(N'dbo.det_param_producto', N'U') IS NULL
    BEGIN
        create table [det_param_producto] ([id_param] int identity primary key not null, [nom_operacion] nvarchar(100) not null, [producto] nvarchar(20) not null, [vigente_desde] date not null, [vigente_hasta] date null, [id_usuario] int not null, [fecha_registro] datetime not null);
        create index [ix_param_producto_vigencia] on [det_param_producto] ([vigente_desde], [vigente_hasta]);
    END

    IF OBJECT_ID(N'dbo.det_param_interes', N'U') IS NULL
    BEGIN
        create table [det_param_interes] ([id_param] int identity primary key not null, [producto] nvarchar(20) not null, [tasa_mora_mensual] decimal(9, 6) not null, [aplica_mora] bit not null, [sigue_calculando_suspendido] bit not null, [vigente_desde] date not null, [vigente_hasta] date null, [id_usuario] int not null, [fecha_registro] datetime not null);
        create index [ix_param_interes_vigencia] on [det_param_interes] ([vigente_desde], [vigente_hasta]);
    END

    IF OBJECT_ID(N'dbo.det_param_convencion', N'U') IS NULL
    BEGIN
        create table [det_param_convencion] ([id_param] int identity primary key not null, [base_dias] smallint not null, [origen_mora] nvarchar(30) not null, [base_incluye_interes] bit not null, [siesa_manda_sobre_base] bit not null, [tarifa_renta] decimal(7, 4) not null, [vigente_desde] date not null, [vigente_hasta] date null, [id_usuario] int not null, [fecha_registro] datetime not null);
        create index [ix_param_convencion_vigencia] on [det_param_convencion] ([vigente_desde], [vigente_hasta]);
    END

    -- ------------------------------------------------------------------
    -- 2. Corte y snapshot (2026_08_31_100100_create_det_corte_tables)
    -- ------------------------------------------------------------------

    IF OBJECT_ID(N'dbo.det_corte', N'U') IS NULL
    BEGIN
        create table [det_corte] ([id_corte] int identity primary key not null, [fecha_corte] date not null, [fecha_comparacion] date null, [estado] nvarchar(12) not null, [id_corte_anterior] int null, [id_usuario] int not null, [fecha_creacion] datetime not null, [fecha_ejecucion] datetime null, [duracion_ms] int null, [filas_origen] int null, [suma_capital_origen] decimal(19, 4) null, [suma_interes_origen] decimal(19, 4) null, [hash_datos] nchar(64) null, [hash_parametros] nchar(64) null, [modo_compatibilidad_excel] bit not null default '0', [observacion] nvarchar(500) null);
        create unique index [ux_det_corte_fecha] on [det_corte] ([fecha_corte]);
    END

    IF OBJECT_ID(N'dbo.det_corte_param_rango_mora', N'U') IS NULL
    BEGIN
        create table [det_corte_param_rango_mora] ([id_corte] int not null, [codigo] nchar(1) not null, [dias_desde] int not null, [dias_hasta] int not null, [etiqueta] nvarchar(40) not null, [pct_deterioro_contable] decimal(7, 4) not null, [orden] tinyint not null);
        alter table [det_corte_param_rango_mora] add constraint [pk_det_corte_param_rango] primary key ([id_corte], [codigo]);
    END

    IF OBJECT_ID(N'dbo.det_corte_param_producto', N'U') IS NULL
    BEGIN
        create table [det_corte_param_producto] ([id_corte] int not null, [nom_operacion] nvarchar(100) not null, [producto] nvarchar(20) not null);
        alter table [det_corte_param_producto] add constraint [pk_det_corte_param_producto] primary key ([id_corte], [nom_operacion]);
    END

    IF OBJECT_ID(N'dbo.det_corte_param_interes', N'U') IS NULL
    BEGIN
        create table [det_corte_param_interes] ([id_corte] int not null, [producto] nvarchar(20) not null, [tasa_mora_mensual] decimal(9, 6) not null, [aplica_mora] bit not null, [sigue_calculando_suspendido] bit not null);
        alter table [det_corte_param_interes] add constraint [pk_det_corte_param_interes] primary key ([id_corte], [producto]);
    END

    IF OBJECT_ID(N'dbo.det_corte_param_convencion', N'U') IS NULL
    BEGIN
        create table [det_corte_param_convencion] ([id_corte] int not null, [base_dias] smallint not null, [origen_mora] nvarchar(30) not null, [base_incluye_interes] bit not null, [siesa_manda_sobre_base] bit not null, [tarifa_renta] decimal(7, 4) not null);
        alter table [det_corte_param_convencion] add constraint [det_corte_param_convencion_id_corte_primary] primary key ([id_corte]);
    END

    IF OBJECT_ID(N'dbo.det_corte_detalle_cuota', N'U') IS NULL
    BEGIN
        create table [det_corte_detalle_cuota] ([id_corte] int not null, [id_operacion] int not null, [id_cuota] int not null, [id_detalle_operacion] int null, [id_ano] nvarchar(4) null, [id_periodo] nvarchar(2) null, [id_cliente] nvarchar(20) null, [cliente] nvarchar(255) null, [id_pagador] nvarchar(20) null, [pagador] nvarchar(255) null, [id_comisionista] nvarchar(20) null, [comisionista] nvarchar(255) null, [fec_operacion] date null, [tasa_interes_cliente] decimal(12, 6) null, [saldo_capital] decimal(19, 4) null, [saldo_intereses] decimal(19, 4) null, [saldo_intereses_causado] decimal(19, 4) null, [saldo_mora] decimal(19, 4) null, [saldo_mora_causado] decimal(19, 4) null, [saldo_admon] decimal(19, 4) null, [saldo_neto_recibir] decimal(19, 4) null, [fec_inicial_corriente] date null, [fec_final_corriente] date null, [fec_inicial_mora] date null, [dias_vencidos] int null, [dias_corriente] int null, [reliquida_mora] decimal(19, 4) null, [tipo_operacion] int null, [nom_operacion] nvarchar(255) null, [nom_mod_operacion] nvarchar(255) null, [valor_credito] decimal(19, 4) null, [producto] nvarchar(20) null, [dias_mora_cuota] int null, [dias_mora_operacion] int null, [calificacion_abc] nvarchar(12) null, [rango_codigo] nchar(1) null, [capital_corriente] decimal(19, 4) null, [interes_corriente] decimal(19, 4) null, [capital_vencido] decimal(19, 4) null, [interes_vencido] decimal(19, 4) null, [interes_mora] decimal(19, 4) null, [estado_cuota] nvarchar(10) null, [capital_mes_anterior] decimal(19, 4) null);
        alter table [det_corte_detalle_cuota] add constraint [pk_det_corte_detalle_cuota] primary key ([id_corte], [id_operacion], [id_cuota]);
        create index [ix_det_detalle_cliente] on [det_corte_detalle_cuota] ([id_corte], [id_cliente]);
    END

    -- ------------------------------------------------------------------
    -- 3. Resultados, cuadres, staging y bitacora
    --    (2026_08_31_100200_create_det_resultado_tables)
    -- ------------------------------------------------------------------

    IF OBJECT_ID(N'dbo.det_deterioro_operacion', N'U') IS NULL
    BEGIN
        create table [det_deterioro_operacion] ([id_corte] int not null, [id_operacion] int not null, [id_cliente] nvarchar(20) null, [cliente] nvarchar(255) null, [producto] nvarchar(20) null, [nom_operacion] nvarchar(255) null, [fec_operacion] date null, [fec_inicial_mora] date null, [cuotas] int not null, [capital_corriente] decimal(19, 4) not null, [interes_corriente] decimal(19, 4) not null, [capital_vencido] decimal(19, 4) not null, [interes_vencido] decimal(19, 4) not null, [interes_mora] decimal(19, 4) not null, [saldo_admon] decimal(19, 4) not null, [dias_mora_operacion] int null, [calificacion_abc] nvarchar(12) null, [rango_codigo] nchar(1) null, [base_deterioro] decimal(19, 4) not null, [pct_contable] decimal(7, 4) null, [deterioro_contable] decimal(19, 4) null, [capital_mes_anterior] decimal(19, 4) null, [variacion_capital] decimal(19, 4) null);
        alter table [det_deterioro_operacion] add constraint [pk_det_deterioro_operacion] primary key ([id_corte], [id_operacion]);
        create index [ix_det_deterioro_prod_rango] on [det_deterioro_operacion] ([id_corte], [producto], [rango_codigo]);
    END

    IF OBJECT_ID(N'dbo.det_corte_cuadre', N'U') IS NULL
    BEGIN
        create table [det_corte_cuadre] ([id_corte] int not null, [codigo] nvarchar(10) not null, [descripcion] nvarchar(200) not null, [valor_detalle] decimal(19, 4) null, [valor_resumen] decimal(19, 4) null, [diferencia] decimal(19, 4) null, [tolerancia] decimal(19, 4) not null default '1', [estado] nvarchar(10) not null);
        alter table [det_corte_cuadre] add constraint [pk_det_corte_cuadre] primary key ([id_corte], [codigo]);
    END

    IF OBJECT_ID(N'dbo.det_validacion_excel', N'U') IS NULL
    BEGIN
        create table [det_validacion_excel] ([id_validacion] int identity primary key not null, [id_corte] int not null, [hoja] nvarchar(20) not null, [id_operacion] int null, [columna] nvarchar(40) not null, [valor_excel] decimal(19, 4) null);
        create index [ix_det_validacion] on [det_validacion_excel] ([id_corte], [hoja], [id_operacion]);
    END

    IF OBJECT_ID(N'dbo.det_bitacora', N'U') IS NULL
    BEGIN
        create table [det_bitacora] ([id_evento] bigint identity primary key not null, [accion] nvarchar(40) not null, [id_corte] int null, [id_operacion] int null, [valor_anterior] nvarchar(max) null, [valor_nuevo] nvarchar(max) null, [id_usuario] int not null, [doc_usuario] nvarchar(20) null, [ip] nvarchar(45) null, [fecha] datetime not null);
        create index [ix_det_bitacora_corte] on [det_bitacora] ([id_corte], [fecha]);
    END

    COMMIT TRANSACTION;
    PRINT 'Paso 01 (fase 1 deterioro) aplicado sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Paso 01 revertido: no se aplico ningun cambio.';
    THROW;
END CATCH
GO
