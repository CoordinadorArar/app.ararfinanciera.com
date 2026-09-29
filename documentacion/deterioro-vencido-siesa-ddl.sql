/*
 * Deterioro de Cartera - Capital e interes vencido segun SIESA (informativo).
 * Esquema para ArarFinanciera_PRUEBAS.
 *
 * Equivale a `php artisan migrate` de
 * 2026_09_28_100000_add_vencido_siesa_a_det_deterioro.php.
 *
 * ORDEN DE DESPLIEGUE: va DESPUES de 2026_09_23_100000 (prorroga).
 *
 * det_corte_saldo_siesa.saldo_vencido: parte del saldo de cada fila del snapshot
 * cuyo f353_fecha_vcto es <= fecha de corte. No cambia el agrupamiento ni saldo.
 *
 * det_deterioro_operacion.capital_vencido_siesa / interes_vencido_siesa: solo
 * informativas. No entran en base_deterioro ni en ningun calculo o control.
 * Quedan NULL hasta RECALCULAR el corte, y NULL en operaciones sin saldo SIESA.
 */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'ArarFinanciera_PRUEBAS'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera_PRUEBAS.', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    IF COL_LENGTH(N'dbo.det_corte_saldo_siesa', N'saldo_vencido') IS NULL
        alter table [det_corte_saldo_siesa] add [saldo_vencido] decimal(19, 4) null;

    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'capital_vencido_siesa') IS NULL
        alter table [det_deterioro_operacion] add [capital_vencido_siesa] decimal(19, 4) null;

    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'interes_vencido_siesa') IS NULL
        alter table [det_deterioro_operacion] add [interes_vencido_siesa] decimal(19, 4) null;

    COMMIT TRANSACTION;
    PRINT 'Vencido SIESA aplicado sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH
GO


/*
 * REVERSION (equivalente a down()). Descomentar solo sobre ArarFinanciera_PRUEBAS.

ALTER TABLE det_deterioro_operacion DROP COLUMN capital_vencido_siesa, interes_vencido_siesa;
ALTER TABLE det_corte_saldo_siesa DROP COLUMN saldo_vencido;

 */


SELECT tabla = OBJECT_NAME(c.object_id), columna = c.name, tipo = t.name, c.precision, c.scale, c.is_nullable
FROM sys.columns c
INNER JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE (c.object_id = OBJECT_ID(N'dbo.det_deterioro_operacion')
       AND c.name IN (N'capital_vencido_siesa', N'interes_vencido_siesa'))
   OR (c.object_id = OBJECT_ID(N'dbo.det_corte_saldo_siesa') AND c.name = N'saldo_vencido');
GO
