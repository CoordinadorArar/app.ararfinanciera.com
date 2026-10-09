/*
 * ======================================================================
 *  ROLLBACK EN PRODUCCION - R15
 *  Revierte: pasos 06, 07 y 08 (deterioro fase 5a: suspension de intereses y sus causales)
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
 *  ATENCION: se pierden TODAS las marcas de suspension (incluidas las
 *  registradas por usuarios o por cargues operativos) y las causales; borra el
 *  cuadre C-SUSPENSION. Ejecutar despues de R11. Bloque de reversion de
 *  deterioro-fase5-ddl.sql.
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

    DELETE FROM det_corte_cuadre WHERE codigo = N'C-SUSPENSION';

    DECLARE @sql NVARCHAR(MAX) = '';
    SELECT @sql += 'ALTER TABLE [dbo].[det_deterioro_operacion] DROP CONSTRAINT '
                 + OBJECT_NAME([default_object_id]) + ';'
    FROM sys.columns
    WHERE [object_id] = OBJECT_ID('[dbo].[det_deterioro_operacion]')
      AND [name] IN ('suspendida') AND [default_object_id] <> 0;
    EXEC(@sql);

    ALTER TABLE det_deterioro_operacion DROP COLUMN
        suspendida, id_suspension, interes_vencido_congelado,
        base_congelada, interes_no_facturado;

    DROP INDEX ux_det_suspension_operacion_activa ON det_suspension_interes;
    DROP TABLE det_suspension_interes;
    DROP TABLE det_corte_param_causal_suspension;
    DROP TABLE det_param_causal_suspension;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH
GO
