/*
 * ======================================================================
 *  ROLLBACK EN PRODUCCION - R19 - NORMALMENTE NO SE EJECUTA
 *  Revierte: pasos 01 y 02 (deterioro fase 1)
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
 *  GENERADO desde los down() de las tres migraciones de 2026_08_31.
 *  La fase 1 YA EXISTIA en produccion antes de esta migracion, con cortes
 *  calculados: borrarla destruye datos que no son de esta migracion. Por eso
 *  este script ABORTA si det_corte tiene alguna fila. Solo sirve si la fase 1
 *  se creo por primera vez en esta ventana y todavia no hay cortes.
 *  Tambien quita de migrations las tres migraciones de la fase 1.
 *  Ejecutar despues de R01 a R18.
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

IF OBJECT_ID(N'dbo.det_corte', N'U') IS NOT NULL AND EXISTS (SELECT 1 FROM det_corte)
BEGIN
    THROW 50004, 'det_corte tiene cortes: la fase 1 existia antes de la migracion. No se revierte.', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    DROP TABLE det_bitacora;
    DROP TABLE det_validacion_excel;
    DROP TABLE det_corte_cuadre;
    DROP TABLE det_deterioro_operacion;

    DROP TABLE det_corte_detalle_cuota;
    DROP TABLE det_corte_param_convencion;
    DROP TABLE det_corte_param_interes;
    DROP TABLE det_corte_param_producto;
    DROP TABLE det_corte_param_rango_mora;
    DROP TABLE det_corte;

    DROP TABLE det_param_convencion;
    DROP TABLE det_param_interes;
    DROP TABLE det_param_producto;
    DROP TABLE det_param_rango_mora;

    IF OBJECT_ID(N'dbo.migrations', N'U') IS NOT NULL
        DELETE FROM migrations
        WHERE migration IN (
            N'2026_08_31_100000_create_det_parametros_tables',
            N'2026_08_31_100100_create_det_corte_tables',
            N'2026_08_31_100200_create_det_resultado_tables');

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH
GO
