/*
 * MIGRACION A PRODUCCION - Paso 07
 * Deterioro de Cartera - Causales de suspension de intereses (D-05).
 *
 * GENERADO para esta migracion: equivale a las tres primeras filas de
 * `php artisan db:seed --class=DeterioroCausalSuspensionSeeder`
 * (FALLECIMIENTO, INSOLVENCIA, COBRO_JURIDICO). En pruebas se sembraron con el
 * seeder y no tenian DDL. Las dos restantes del seeder (FIN_CUOTAS y
 * SIN_DETERMINAR) las siembra el paso 08, copia de
 * deterioro-causal-fin-cuotas-ddl.sql, de modo que 07 + 08 = seeder completo y
 * en el mismo orden de id_param que en pruebas.
 *
 * Idempotente por codigo, igual que el seeder: si la causal ya existe (vigente
 * o no) no se vuelve a insertar.
 *
 * Prerrequisitos: paso 06 (crea det_param_causal_suspension).
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

    IF OBJECT_ID(N'dbo.det_param_causal_suspension', N'U') IS NULL
    BEGIN
        THROW 50000, 'Falta det_param_causal_suspension: aplique antes el paso 06.', 1;
    END

    DECLARE @fecha_registro nvarchar(19) = CONVERT(nvarchar(19), GETDATE(), 126);
    DECLARE @vigente_desde date = '2016-01-01';
    DECLARE @id_usuario int = 0;

    IF NOT EXISTS (SELECT 1 FROM det_param_causal_suspension WHERE codigo = N'FALLECIMIENTO')
        INSERT INTO det_param_causal_suspension
            (codigo, descripcion, activa, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'FALLECIMIENTO', N'Fallecimiento del deudor sin que la aseguradora pague', 1, @vigente_desde, NULL, @id_usuario, @fecha_registro);

    IF NOT EXISTS (SELECT 1 FROM det_param_causal_suspension WHERE codigo = N'INSOLVENCIA')
        INSERT INTO det_param_causal_suspension
            (codigo, descripcion, activa, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'INSOLVENCIA', N'Admisión del deudor a un proceso de insolvencia', 1, @vigente_desde, NULL, @id_usuario, @fecha_registro);

    IF NOT EXISTS (SELECT 1 FROM det_param_causal_suspension WHERE codigo = N'COBRO_JURIDICO')
        INSERT INTO det_param_causal_suspension
            (codigo, descripcion, activa, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'COBRO_JURIDICO', N'Paso de la operación a cobro jurídico', 1, @vigente_desde, NULL, @id_usuario, @fecha_registro);

    COMMIT TRANSACTION;
    PRINT 'Paso 07 (causales de suspension) aplicado sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Paso 07 revertido: no se aplico ningun cambio.';
    THROW;
END CATCH
GO
