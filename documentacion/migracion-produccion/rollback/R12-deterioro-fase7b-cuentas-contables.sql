/*
 * ======================================================================
 *  ROLLBACK EN PRODUCCION - R12
 *  Revierte: paso 12 (deterioro fase 7b: cuentas contables del asiento)
 * ======================================================================
 *
 *  !!! ADVERTENCIA: SCRIPT DESTRUCTIVO SOBRE LA BASE DE PRODUCCION !!!
 *
 *  - Ejecutar SOLO si la migracion fallo o se decidio revertirla, con la
 *    aplicacion detenida y DESPUES de confirmar que existe el respaldo
 *    completo tomado antes de empezar (ver README).
 *  - Los rollback van en orden: R01, R02, ... R19. Ejecute solo los de los
 *    pasos que SI se aplicaron (salida de los pasos 00 y 99). Un rollback de
 *    un paso no aplicado falla dentro de su transaccion y no cambia nada.
 *  - Si hay duda, restaurar el respaldo es mas seguro que este script.
 *  - Para ejecutarlo hay que editar @confirmo = 1 abajo: es a proposito.
 *
 *  ATENCION: se pierden las cuentas que Contabilidad haya definido y la copia
 *  congelada por corte. Bloque de reversion de deterioro-fase7b-ddl.sql.
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

DECLARE @confirmo bit = 0;

IF @confirmo = 0
BEGIN
    THROW 50003, 'Reversion NO confirmada: lea la advertencia y cambie @confirmo a 1 en este archivo.', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    DECLARE @sql NVARCHAR(MAX) = '';
    SELECT @sql += 'ALTER TABLE [dbo].[det_corte] DROP CONSTRAINT '
                 + OBJECT_NAME([default_object_id]) + ';'
    FROM sys.columns
    WHERE [object_id] = OBJECT_ID('[dbo].[det_corte]')
      AND [name] IN ('cuentas_congeladas') AND [default_object_id] <> 0;
    EXEC(@sql);

    ALTER TABLE det_corte DROP COLUMN cuentas_congeladas;

    DROP TABLE det_corte_param_cuenta_contable;
    DROP TABLE det_param_cuenta_contable;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH
GO
