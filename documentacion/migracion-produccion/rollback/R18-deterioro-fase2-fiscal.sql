/*
 * ======================================================================
 *  ROLLBACK EN PRODUCCION - R18
 *  Revierte: paso 03 (deterioro fase 2: calculo fiscal y su semilla)
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
 *  ATENCION: se pierde el acumulado fiscal (det_fiscal_acumulado), incluido el
 *  1399 historico cargado; borra los cuadres C-FISCAL, C-FISCAL-ACUM y
 *  C-FISCAL-TOPE y devuelve det_corte_cuadre.codigo a nvarchar(10), lo que
 *  FALLA si queda algun codigo de mas de 10 caracteres de otra fase (ejecute
 *  antes R08 a R17). Bloque de reversion de deterioro-fase2-ddl.sql.
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

    DELETE FROM det_corte_cuadre WHERE codigo IN (N'C-FISCAL', N'C-FISCAL-ACUM', N'C-FISCAL-TOPE');

    ALTER TABLE det_corte_cuadre DROP CONSTRAINT pk_det_corte_cuadre;
    ALTER TABLE det_corte_cuadre ALTER COLUMN codigo nvarchar(10) NOT NULL;
    ALTER TABLE det_corte_cuadre ADD CONSTRAINT pk_det_corte_cuadre PRIMARY KEY (id_corte, codigo);

    DROP TABLE det_fiscal_acumulado;

    ALTER TABLE det_deterioro_operacion DROP COLUMN
        saldo_siesa, saldo_topado, fiscal_acumulado_anterior,
        deterioro_fiscal_individual, deterioro_fiscal_general, deduccion_fiscal_ano;

    DROP TABLE det_corte_param_fiscal_rango;
    DROP TABLE det_corte_param_fiscal;
    DROP TABLE det_param_fiscal_rango;
    DROP TABLE det_param_fiscal;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH
GO
