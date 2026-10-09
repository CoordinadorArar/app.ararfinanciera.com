/*
 * MIGRACION A PRODUCCION - Paso 02
 * Deterioro de Cartera - Semilla de las parametricas de la fase 1.
 *
 * GENERADO para esta migracion: equivale a la parte de fase 1 de
 * `php artisan db:seed --class=DeterioroParametrosSeeder` (rangos de mora,
 * mapeo de producto, interes de mora y convencion). La parte fiscal del mismo
 * seeder la siembra el paso 03 (copia de deterioro-fase2-ddl.sql).
 *
 * Mismo criterio de idempotencia del seeder: cada tabla se siembra SOLO si
 * esta vacia. Si produccion ya tiene parametricas (lo esperado, porque la
 * fase 1 ya corre alli), no se toca ninguna fila. Este script nunca copia los
 * valores ajustados en ArarFinanciera_PRUEBAS: si negocio ajusto alguno en
 * pruebas, debe replicarse a mano por la pantalla/parametrica (ver README).
 *
 * Formato ISO 8601 con T en fecha_registro: el login del servidor usa idioma
 * espanol y 'aaaa-mm-dd hh:mm:ss' se interpretaria como dd/mm/aaaa.
 *
 * Prerrequisitos: paso 01.
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

    DECLARE @fecha_registro nvarchar(19) = CONVERT(nvarchar(19), GETDATE(), 126);
    DECLARE @vigente_desde date = '2016-01-01';
    DECLARE @id_usuario int = 0;

    IF NOT EXISTS (SELECT 1 FROM det_param_rango_mora)
    BEGIN
        INSERT INTO det_param_rango_mora
            (codigo, dias_desde, dias_hasta, etiqueta, pct_deterioro_contable, orden, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'A',   0,     30, N'0 hasta 30',      0.0000, 1, @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'B',  31,     90, N'31 hasta 90',     0.0800, 2, @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'C',  91,    180, N'91 hasta 180',    0.2300, 3, @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'D', 181,    360, N'181 hasta 360',   0.5300, 4, @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'E', 361,    720, N'361 hasta 720',   0.7800, 5, @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'F', 721, 999999, N'721 en adelante', 1.0000, 6, @vigente_desde, NULL, @id_usuario, @fecha_registro);
    END

    IF NOT EXISTS (SELECT 1 FROM det_param_producto)
    BEGIN
        INSERT INTO det_param_producto
            (nom_operacion, producto, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'FINANCIACION',         N'FINANCIACION', @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'LETRA DE CAMBIO',      N'FACTORING',    @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'LIBRANZAS',            N'LIBRANZAS',    @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'DESCUENTO DE CHEQUES', N'FACTORING',    @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'FACTORING',            N'FACTORING',    @vigente_desde, NULL, @id_usuario, @fecha_registro);
    END

    IF NOT EXISTS (SELECT 1 FROM det_param_interes)
    BEGIN
        INSERT INTO det_param_interes
            (producto, tasa_mora_mensual, aplica_mora, sigue_calculando_suspendido, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'FINANCIACION', 0.023300, 1, 0, @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'LIBRANZAS',    0.023300, 1, 0, @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'FACTORING',    0.023300, 0, 1, @vigente_desde, NULL, @id_usuario, @fecha_registro);
    END

    IF NOT EXISTS (SELECT 1 FROM det_param_convencion)
    BEGIN
        INSERT INTO det_param_convencion
            (base_dias, origen_mora, base_incluye_interes, siesa_manda_sobre_base, tarifa_renta, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (360, N'FEC_INICIAL_MORA', 1, 0, 0.3500, @vigente_desde, NULL, @id_usuario, @fecha_registro);
    END

    COMMIT TRANSACTION;
    PRINT 'Paso 02 (semilla parametricas fase 1) aplicado sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Paso 02 revertido: no se aplico ningun cambio.';
    THROW;
END CATCH
GO
