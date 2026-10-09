/*
 * ======================================================================
 *  ROLLBACK EN PRODUCCION - R13
 *  Revierte: pasos 10 y 11 (deterioro fase 7a: cierre, reapertura, salidas y sus causales)
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
 *  ATENCION: borra del acumulado fiscal lo escrito por cierres de diciembre
 *  (origen CIERRE_DICIEMBRE); se pierde la clasificacion de las bajas y el
 *  motivo/foto de los cierres con salvedad; borra los cuadres C-PRORROGA,
 *  C-SALIDAS y C-CIERRE-FISCAL. Los cortes CERRADOS siguen cerrados pero sin
 *  fecha ni autor. Las causales de salida (paso 11) desaparecen con su tabla.
 *  Bloque de reversion de deterioro-fase7-ddl.sql.
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

    DELETE FROM det_fiscal_acumulado WHERE origen = N'CIERRE_DICIEMBRE';

    DELETE FROM det_corte_cuadre
    WHERE codigo IN (N'C-PRORROGA', N'C-SALIDAS', N'C-CIERRE-FISCAL');

    DECLARE @sql NVARCHAR(MAX) = '';
    SELECT @sql += 'ALTER TABLE [dbo].[det_corte] DROP CONSTRAINT '
                 + OBJECT_NAME([default_object_id]) + ';'
    FROM sys.columns
    WHERE [object_id] = OBJECT_ID('[dbo].[det_corte]')
      AND [name] IN ('cerrado_con_salvedad') AND [default_object_id] <> 0;
    EXEC(@sql);

    ALTER TABLE det_corte DROP COLUMN fecha_cierre, id_usuario_cierre,
        cerrado_con_salvedad, motivo_salvedad, foto_salvedad,
        fecha_reapertura, id_usuario_reapertura, motivo_reapertura;

    DROP TABLE det_salida_operacion;
    DROP TABLE det_corte_param_causal_salida;
    DROP TABLE det_param_causal_salida;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH
GO
