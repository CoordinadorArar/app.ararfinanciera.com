/*
 * MIGRACION A PRODUCCION - Paso 04
 * Copia de documentacion/deterioro-fase3-ddl.sql. Lo unico que cambia respecto del original es
 * esta cabecera, el bloque de opciones SET inicial, la guarda de base
 * (ArarFinanciera_PRUEBAS -> ArarFinanciera) y las lineas que indicaban
 * ejecutar sobre pruebas. La logica es identica a la validada en pruebas.
 * Equivale a: migrate 2026_09_02_100000_create_det_diferido_tables
 * Prerrequisitos: paso 03. El cargue del 1399 debe hacerse antes de CALCULAR el primer corte (ver README).
 * La reversion para produccion esta en rollback/ (no descomentar el bloque de
 * abajo).
 */

SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET NUMERIC_ROUNDABORT OFF;
GO

/*
 * Deterioro de Cartera - Fase 3 (comparativo contable vs fiscal e impuesto diferido)
 * Esquema para ArarFinanciera (produccion).
 *
 * Equivale a `php artisan migrate` de
 * 2026_09_02_100000_create_det_diferido_tables.php. El DDL reproduce
 * literalmente el texto que emite Illuminate\Database\Schema\Grammars\
 * SqlServerGrammar para que lo validado aqui sea exactamente lo que despues
 * corra en produccion por migracion.
 *
 * ORDEN DE DESPLIEGUE: fase 2 -> cargar el 1399 (det_fiscal_acumulado) -> fase 3.
 * Las columnas de esta fase se calculan a partir de fiscal_acumulado_anterior,
 * deduccion_fiscal_ano y saldo_topado, que son de la fase 2 y solo tienen valor
 * correcto cuando el acumulado historico ya esta cargado. Invertir el orden deja
 * la diferencia temporaria igual al deterioro contable y el impuesto diferido
 * sobrestimado.
 *
 * No se crea ninguna tabla y NO hay que tocar la llave primaria de
 * det_corte_cuadre: la fase 2 ya dejo la columna codigo en nvarchar(20) y los
 * codigos nuevos (C-DIF-TEMP, C-DIFERIDO, C-REVERSION) caben.
 *
 * Es idempotente y transaccional: se puede repetir sin romper y un fallo a mitad
 * no deja el esquema partido.
 */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'ArarFinanciera'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera (produccion).', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    -- ------------------------------------------------------------------
    -- 1. Comparativo contable vs fiscal por operacion (seccion 11)
    -- ------------------------------------------------------------------

    -- La migracion agrega las cuatro columnas en un solo ALTER; aqui van una a
    -- una para poder comprobar cada existencia. El orden de creacion es el
    -- mismo, de modo que el esquema resultante no cambia.

    -- fiscal_acumulado_anterior + deduccion_fiscal_ano.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'deterioro_fiscal_acumulado') IS NULL
        alter table [det_deterioro_operacion] add [deterioro_fiscal_acumulado] decimal(19, 4) null;

    -- Contable - fiscal acumulado. Conserva el signo: negativa es pasivo por
    -- impuesto diferido, y ocurre cuando el acumulado fiscal supera al contable.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'diferencia_temporaria') IS NULL
        alter table [det_deterioro_operacion] add [diferencia_temporaria] decimal(19, 4) null;

    -- Diferencia temporaria x det_param_convencion.tarifa_renta (art. 240), no
    -- x el pct_anual del 33 % del art. 145 ET, que es otra cosa.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'impuesto_diferido_activo') IS NULL
        alter table [det_deterioro_operacion] add [impuesto_diferido_activo] decimal(19, 4) null;

    -- Ano gravable proyectado en que la operacion completa el 100 % fiscal.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'ano_reversion_fiscal') IS NULL
        alter table [det_deterioro_operacion] add [ano_reversion_fiscal] smallint null;

    COMMIT TRANSACTION;
    PRINT 'Fase 3 aplicada sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Fase 3 revertida: no se aplico ningun cambio.';
    THROW;
END CATCH
GO


/*
 * ----------------------------------------------------------------------
 * REVERSION (equivalente a down()). Bloque comentado a proposito.
 * ----------------------------------------------------------------------
 *
 * ATENCION: ademas de soltar las cuatro columnas, borra las filas de los tres
 * cuadres de esta fase en det_corte_cuadre (C-DIF-TEMP, C-DIFERIDO y
 * C-REVERSION), que sin sus columnas quedarian huerfanos listandose en
 * pantalla. Los cortes ya calculados pierden esos renglones de forma
 * irreversible y el comparativo hay que recalcularlo.
 *
 * La llave primaria de det_corte_cuadre no se toca: el ancho de codigo lo
 * gobierna la fase 2.
 *
 * NO descomentar: en produccion use rollback/ de migracion-produccion.

BEGIN TRY
    BEGIN TRANSACTION;

    DELETE FROM det_corte_cuadre WHERE codigo IN (N'C-DIF-TEMP', N'C-DIFERIDO', N'C-REVERSION');

    ALTER TABLE det_deterioro_operacion DROP COLUMN
        deterioro_fiscal_acumulado, diferencia_temporaria,
        impuesto_diferido_activo, ano_reversion_fiscal;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */
