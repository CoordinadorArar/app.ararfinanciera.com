/*
 * MIGRACION A PRODUCCION - Paso 24
 * Registro de las migraciones de 2026 en la tabla `migrations` de Laravel.
 *
 * GENERADO para esta migracion. config/database.php define 'migrations' =>
 * 'migrations' y `php artisan migrate` usa la conexion por defecto (sqlsrv =
 * DB_DATABASE = ArarFinanciera). Como el esquema se aplico por SQL y no por
 * artisan, sin este paso un `php artisan migrate` futuro intentaria recrear
 * tablas y columnas existentes y fallaria (varias migraciones no tienen guarda
 * hasTable/hasColumn).
 *
 * Que hace:
 * - Si la tabla `migrations` no existe, NO la crea: informa y termina sin
 *   cambios (ver README).
 * - Registra cada una de las 19 migraciones de 2026 SOLO si (a) no esta ya
 *   registrada y (b) su objeto testigo existe en la base. Si un testigo falta,
 *   la migracion NO se registra y se informa: significa que un paso anterior
 *   no se aplico.
 * - Todas las que se registren en esta corrida van con el mismo batch:
 *   MAX(batch) + 1.
 *
 * Prerrequisitos: pasos 01 a 21.
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

IF OBJECT_ID(N'dbo.migrations', N'U') IS NULL
BEGIN
    PRINT 'La tabla migrations no existe en ' + DB_NAME() + ': no se registra nada. Ver README (seccion Registro de migraciones).';
    RETURN;
END

BEGIN TRY
    BEGIN TRANSACTION;

    DECLARE @m TABLE (orden int NOT NULL, migration nvarchar(255) NOT NULL, aplicada bit NOT NULL);

    INSERT INTO @m (orden, migration, aplicada) VALUES
        ( 1, N'2026_08_31_100000_create_det_parametros_tables',            CASE WHEN OBJECT_ID(N'dbo.det_param_convencion', N'U') IS NOT NULL THEN 1 ELSE 0 END),
        ( 2, N'2026_08_31_100100_create_det_corte_tables',                 CASE WHEN OBJECT_ID(N'dbo.det_corte_detalle_cuota', N'U') IS NOT NULL THEN 1 ELSE 0 END),
        ( 3, N'2026_08_31_100200_create_det_resultado_tables',             CASE WHEN OBJECT_ID(N'dbo.det_bitacora', N'U') IS NOT NULL THEN 1 ELSE 0 END),
        ( 4, N'2026_09_01_100000_create_det_fiscal_tables',                CASE WHEN OBJECT_ID(N'dbo.det_fiscal_acumulado', N'U') IS NOT NULL THEN 1 ELSE 0 END),
        ( 5, N'2026_09_02_100000_create_det_diferido_tables',              CASE WHEN COL_LENGTH(N'dbo.det_deterioro_operacion', N'ano_reversion_fiscal') IS NOT NULL THEN 1 ELSE 0 END),
        ( 6, N'2026_09_03_100000_create_det_historico_tables',             CASE WHEN COL_LENGTH(N'dbo.det_corte_cuadre', N'informativo') IS NOT NULL THEN 1 ELSE 0 END),
        ( 7, N'2026_09_03_110000_create_det_suspension_tables',            CASE WHEN COL_LENGTH(N'dbo.det_deterioro_operacion', N'interes_no_facturado') IS NOT NULL THEN 1 ELSE 0 END),
        ( 8, N'2026_09_04_100000_create_det_siesa_tables',                 CASE WHEN COL_LENGTH(N'dbo.det_deterioro_operacion', N'origen_base') IS NOT NULL THEN 1 ELSE 0 END),
        ( 9, N'2026_09_05_100000_create_det_cierre_tables',                CASE WHEN COL_LENGTH(N'dbo.det_corte', N'motivo_reapertura') IS NOT NULL THEN 1 ELSE 0 END),
        (10, N'2026_09_14_100000_create_det_cuenta_contable_tables',       CASE WHEN COL_LENGTH(N'dbo.det_corte', N'cuentas_congeladas') IS NOT NULL THEN 1 ELSE 0 END),
        (11, N'2026_09_15_100000_add_soporte_adjunto_to_det_suspension',   CASE WHEN COL_LENGTH(N'dbo.det_suspension_interes', N'soporte_tipo') IS NOT NULL THEN 1 ELSE 0 END),
        (12, N'2026_09_16_100000_add_duplicada_de_to_det_corte_detalle_cuota', CASE WHEN COL_LENGTH(N'dbo.det_corte_detalle_cuota', N'duplicada_de') IS NOT NULL THEN 1 ELSE 0 END),
        (13, N'2026_09_18_100000_add_atribucion_nota_a_det_siesa',         CASE WHEN COL_LENGTH(N'dbo.det_deterioro_operacion', N'estado_reversion') IS NOT NULL THEN 1 ELSE 0 END),
        (14, N'2026_09_23_100000_add_prorroga_a_det_deterioro',            CASE WHEN COL_LENGTH(N'dbo.det_deterioro_operacion', N'interes_prorroga_siesa') IS NOT NULL THEN 1 ELSE 0 END),
        (15, N'2026_09_28_100000_add_vencido_siesa_a_det_deterioro',       CASE WHEN COL_LENGTH(N'dbo.det_deterioro_operacion', N'interes_vencido_siesa') IS NOT NULL THEN 1 ELSE 0 END),
        (16, N'2026_10_07_100000_credito_fase1_pagadurias',                CASE WHEN OBJECT_ID(N'dbo.ConfiguracionAuditoria', N'U') IS NOT NULL THEN 1 ELSE 0 END),
        (17, N'2026_10_07_110000_credito_fase2_registro',                  CASE WHEN COL_LENGTH(N'dbo.TratamientoDatos', N'IdProceso') IS NOT NULL THEN 1 ELSE 0 END),
        (18, N'2026_10_07_120000_credito_fase3_procesos',                  CASE WHEN OBJECT_ID(N'dbo.ProcesosDocumentos', N'U') IS NOT NULL THEN 1 ELSE 0 END),
        (19, N'2026_10_07_130000_credito_fase4_centrales',                 CASE WHEN OBJECT_ID(N'dbo.ConfiguracionCentrales', N'U') IS NOT NULL THEN 1 ELSE 0 END);

    DECLARE @batch int = (SELECT ISNULL(MAX(batch), 0) + 1 FROM migrations);

    INSERT INTO migrations (migration, batch)
    SELECT m.migration, @batch
    FROM @m m
    WHERE m.aplicada = 1
      AND NOT EXISTS (SELECT 1 FROM migrations x WHERE x.migration = m.migration)
    ORDER BY m.orden;

    DECLARE @registradas int = @@ROWCOUNT;

    COMMIT TRANSACTION;

    PRINT 'Paso 24: ' + CAST(@registradas AS varchar(10)) + ' migraciones registradas con batch ' + CAST(@batch AS varchar(10)) + '.';

    SELECT m.orden, m.migration,
           estado = CASE
                        WHEN x.id IS NOT NULL THEN 'REGISTRADA (batch ' + CAST(x.batch AS varchar(10)) + ')'
                        WHEN m.aplicada = 0 THEN 'NO REGISTRADA: su objeto testigo no existe'
                        ELSE 'NO REGISTRADA'
                    END
    FROM @m m
    LEFT JOIN migrations x ON x.migration = m.migration
    ORDER BY m.orden;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Paso 24 revertido: no se registro ninguna migracion.';
    THROW;
END CATCH
GO
