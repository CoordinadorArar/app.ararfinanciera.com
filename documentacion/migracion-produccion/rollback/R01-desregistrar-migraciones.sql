/*
 * ======================================================================
 *  ROLLBACK EN PRODUCCION - R01
 *  Revierte: paso 24 (registro de migraciones de 2026 en la tabla migrations)
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
 *  Quita de migrations las 16 migraciones de 2026 posteriores a la fase 1.
 *  Las tres de la fase 1 NO se quitan aqui (la fase 1 ya existia en
 *  produccion): solo R19 las quita, si se revierte la fase 1.
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

    DELETE FROM migrations
    WHERE migration IN (
        N'2026_09_01_100000_create_det_fiscal_tables',
        N'2026_09_02_100000_create_det_diferido_tables',
        N'2026_09_03_100000_create_det_historico_tables',
        N'2026_09_03_110000_create_det_suspension_tables',
        N'2026_09_04_100000_create_det_siesa_tables',
        N'2026_09_05_100000_create_det_cierre_tables',
        N'2026_09_14_100000_create_det_cuenta_contable_tables',
        N'2026_09_15_100000_add_soporte_adjunto_to_det_suspension',
        N'2026_09_16_100000_add_duplicada_de_to_det_corte_detalle_cuota',
        N'2026_09_18_100000_add_atribucion_nota_a_det_siesa',
        N'2026_09_23_100000_add_prorroga_a_det_deterioro',
        N'2026_09_28_100000_add_vencido_siesa_a_det_deterioro',
        N'2026_10_07_100000_credito_fase1_pagadurias',
        N'2026_10_07_110000_credito_fase2_registro',
        N'2026_10_07_120000_credito_fase3_procesos',
        N'2026_10_07_130000_credito_fase4_centrales');

    PRINT CAST(@@ROWCOUNT AS varchar(10)) + ' filas quitadas de migrations.';

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH
GO
