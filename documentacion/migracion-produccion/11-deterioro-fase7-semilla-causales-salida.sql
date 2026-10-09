/*
 * MIGRACION A PRODUCCION - Paso 11
 * Deterioro de Cartera - Causales de salida de la base entre cortes (C-2, D-09).
 *
 * GENERADO para esta migracion: equivale a
 * `php artisan db:seed --class=DeterioroCausalSalidaSeeder`, que en pruebas se
 * corrio con el seeder y no tenia DDL. Sin estas filas ninguna baja se puede
 * clasificar y ningun corte con bajas se puede cerrar.
 *
 * Idempotente por codigo, igual que el seeder.
 *
 * Prerrequisitos: paso 10 (crea det_param_causal_salida).
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

    IF OBJECT_ID(N'dbo.det_param_causal_salida', N'U') IS NULL
    BEGIN
        THROW 50000, 'Falta det_param_causal_salida: aplique antes el paso 10.', 1;
    END

    DECLARE @fecha_registro nvarchar(19) = CONVERT(nvarchar(19), GETDATE(), 126);
    DECLARE @vigente_desde date = '2016-01-01';
    DECLARE @id_usuario int = 0;

    IF NOT EXISTS (SELECT 1 FROM det_param_causal_salida WHERE codigo = N'RECAUDO_TOTAL')
        INSERT INTO det_param_causal_salida
            (codigo, descripcion, activa, cierra_fiscal, pide_referencia, orden, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'RECAUDO_TOTAL', N'Recaudo total de la operación', 1, 0, 0, 1, @vigente_desde, NULL, @id_usuario, @fecha_registro);

    IF NOT EXISTS (SELECT 1 FROM det_param_causal_salida WHERE codigo = N'CASTIGO')
        INSERT INTO det_param_causal_salida
            (codigo, descripcion, activa, cierra_fiscal, pide_referencia, orden, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'CASTIGO', N'Castigo de cartera', 1, 1, 0, 2, @vigente_desde, NULL, @id_usuario, @fecha_registro);

    IF NOT EXISTS (SELECT 1 FROM det_param_causal_salida WHERE codigo = N'CIERRE_Y_APERTURA')
        INSERT INTO det_param_causal_salida
            (codigo, descripcion, activa, cierra_fiscal, pide_referencia, orden, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'CIERRE_Y_APERTURA', N'Cierre con apertura de una operación nueva', 1, 0, 1, 3, @vigente_desde, NULL, @id_usuario, @fecha_registro);

    IF NOT EXISTS (SELECT 1 FROM det_param_causal_salida WHERE codigo = N'OTRA')
        INSERT INTO det_param_causal_salida
            (codigo, descripcion, activa, cierra_fiscal, pide_referencia, orden, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'OTRA', N'Otra causa, explicada en la observación', 1, 0, 0, 4, @vigente_desde, NULL, @id_usuario, @fecha_registro);

    COMMIT TRANSACTION;
    PRINT 'Paso 11 (causales de salida) aplicado sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Paso 11 revertido: no se aplico ningun cambio.';
    THROW;
END CATCH
GO
