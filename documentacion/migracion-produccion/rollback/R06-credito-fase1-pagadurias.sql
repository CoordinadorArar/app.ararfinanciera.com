/*
 * ======================================================================
 *  ROLLBACK EN PRODUCCION - R06
 *  Revierte: paso 18 (credito fase 1: configuracion por pagaduria)
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
 *  ATENCION: se pierden las reglas de edad (plazo y seguro) por pagaduria, la
 *  bitacora ConfiguracionAuditoria y las columnas UsaReglaSMMLV y UmbralSMMLV.
 *  EstadoPagaduria NO se elimina (la migracion de 2022 ya la declaraba).
 *  Bloque de reversion de credito-fase1-ddl.sql.
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

    DROP TABLE ConfiguracionAuditoria;
    DROP TABLE PagaduriasReglasEdad;

    DECLARE @sql NVARCHAR(MAX) = N'';
    SELECT @sql += N'ALTER TABLE [dbo].[Pagadurias] DROP CONSTRAINT '
                 + OBJECT_NAME([default_object_id]) + N';'
    FROM sys.columns
    WHERE [object_id] = OBJECT_ID(N'[dbo].[Pagadurias]')
      AND [name] IN (N'UsaReglaSMMLV', N'UmbralSMMLV')
      AND [default_object_id] <> 0;
    EXEC(@sql);

    ALTER TABLE Pagadurias DROP COLUMN UsaReglaSMMLV, UmbralSMMLV;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH
GO
