/*
 * ======================================================================
 *  ROLLBACK EN PRODUCCION - R09
 *  Revierte: paso 15 (deterioro: atribucion del saldo SIESA por notas)
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
 *  GENERADO desde el down() de 2026_09_18_100000_add_atribucion_nota_a_det_siesa
 *  (no habia bloque de reversion previo). ATENCION: borra los cuadres
 *  C-SIESA-DOBLE, C-SIESA-NOTA y C-FISCAL-NOMINAL de todos los cortes y la
 *  atribucion por notas del snapshot. Ejecutar despues de R08.
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

    DELETE FROM det_corte_cuadre WHERE codigo IN (N'C-SIESA-DOBLE', N'C-SIESA-NOTA', N'C-FISCAL-NOMINAL');

    ALTER TABLE det_deterioro_operacion DROP COLUMN valor_nominal_siesa, origen_saldo_siesa, estado_reversion;

    DROP INDEX ix_det_saldo_siesa_nota ON det_corte_saldo_siesa;
    ALTER TABLE det_corte_saldo_siesa DROP COLUMN id_operacion_nota, componente;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH
GO
