/*
 * MIGRACION A PRODUCCION - Paso 15
 * Deterioro de Cartera - Atribucion del saldo de SIESA por las notas del
 * documento.
 *
 * GENERADO para esta migracion: no existia DDL previo. Equivale a
 * `php artisan migrate` de 2026_09_18_100000_add_atribucion_nota_a_det_siesa.php.
 * El DDL reproduce el texto de SqlServerGrammar; la migracion agrega las
 * columnas en un solo ALTER por tabla y aqui van una a una para poder comprobar
 * cada existencia (mismo orden, mismo esquema resultante).
 *
 * - det_corte_saldo_siesa: id_operacion_nota int null, componente nvarchar(12)
 *   null e indice ix_det_saldo_siesa_nota (id_corte, id_operacion_nota).
 * - det_deterioro_operacion: valor_nominal_siesa decimal(19,4) null,
 *   origen_saldo_siesa nvarchar(10) null, estado_reversion nvarchar(14) null.
 *
 * El indice se crea por EXEC porque referencia una columna agregada en el mismo
 * lote y la compilacion del lote fallaria con "Invalid column name".
 *
 * Las columnas nacen nulas: se pueblan al RECALCULAR un corte con el codigo
 * nuevo. Sobre SIESA (UNOEEARAR) este script no hace nada.
 *
 * Prerrequisitos: paso 09 (crea det_corte_saldo_siesa). Los pasos 16 y 17
 * dependen de este.
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

    IF OBJECT_ID(N'dbo.det_corte_saldo_siesa', N'U') IS NULL
    BEGIN
        THROW 50000, 'Falta det_corte_saldo_siesa: aplique antes el paso 09 (fase 6a).', 1;
    END

    IF COL_LENGTH(N'dbo.det_corte_saldo_siesa', N'id_operacion_nota') IS NULL
        alter table [det_corte_saldo_siesa] add [id_operacion_nota] int null;

    IF COL_LENGTH(N'dbo.det_corte_saldo_siesa', N'componente') IS NULL
        alter table [det_corte_saldo_siesa] add [componente] nvarchar(12) null;

    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes
        WHERE object_id = OBJECT_ID(N'dbo.det_corte_saldo_siesa')
          AND name = N'ix_det_saldo_siesa_nota'
    )
        EXEC(N'create index [ix_det_saldo_siesa_nota] on [det_corte_saldo_siesa] ([id_corte], [id_operacion_nota])');

    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'valor_nominal_siesa') IS NULL
        alter table [det_deterioro_operacion] add [valor_nominal_siesa] decimal(19, 4) null;

    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'origen_saldo_siesa') IS NULL
        alter table [det_deterioro_operacion] add [origen_saldo_siesa] nvarchar(10) null;

    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'estado_reversion') IS NULL
        alter table [det_deterioro_operacion] add [estado_reversion] nvarchar(14) null;

    COMMIT TRANSACTION;
    PRINT 'Paso 15 (atribucion por notas SIESA) aplicado sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Paso 15 revertido: no se aplico ningun cambio.';
    THROW;
END CATCH
GO
