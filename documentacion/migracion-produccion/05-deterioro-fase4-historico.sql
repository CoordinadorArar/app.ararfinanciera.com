/*
 * MIGRACION A PRODUCCION - Paso 05
 * Copia de documentacion/deterioro-fase4-ddl.sql. Lo unico que cambia respecto del original es
 * esta cabecera, el bloque de opciones SET inicial, la guarda de base
 * (ArarFinanciera_PRUEBAS -> ArarFinanciera) y las lineas que indicaban
 * ejecutar sobre pruebas. La logica es identica a la validada en pruebas.
 * Equivale a: migrate 2026_09_03_100000_create_det_historico_tables
 * Prerrequisitos: paso 04.
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
 * Deterioro de Cartera - Fase 4 (historico y descomposicion del movimiento del mes)
 * Esquema para ArarFinanciera (produccion).
 *
 * Equivale a `php artisan migrate` de
 * 2026_09_03_100000_create_det_historico_tables.php. El DDL reproduce
 * literalmente el texto que emite Illuminate\Database\Schema\Grammars\
 * SqlServerGrammar para que lo validado aqui sea exactamente lo que despues
 * corra en produccion por migracion.
 *
 * ORDEN DE DESPLIEGUE: fase 2 -> cargar el 1399 (det_fiscal_acumulado) -> fase 3
 * -> fase 4.
 * Esta fase compara el deterioro contable de dos cortes consecutivos, de modo
 * que exige que el corte anterior este calculado con el mismo motor. Si se
 * aplica antes de la fase 3 las columnas se crean igual, pero la pantalla de
 * evolucion mezclaria un corte con comparativo fiscal y otro sin el.
 *
 * No se crea ninguna tabla y NO hay que tocar la llave primaria de
 * det_corte_cuadre: la fase 2 ya dejo la columna codigo en nvarchar(20) y los
 * codigos nuevos (C-MOVIMIENTO, C-VARIACION) caben. Si se agregan dos columnas
 * a det_corte_cuadre -motivo e informativo- para sacar de descripcion el texto
 * que hoy se le concatena y que la dejaba a 18 caracteres del limite.
 *
 * Las bajas del periodo -operaciones del corte anterior ausentes en el actual-
 * se detectan por consulta contra los dos cortes y no se persisten: la tabla
 * salida_operacion, la clasificacion de la baja y el control C-2 son de la
 * fase 7.
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
    -- 1. Movimiento del mes por operacion (RN-10, seccion 10 paso 10)
    -- ------------------------------------------------------------------

    -- La migracion agrega las tres columnas en un solo ALTER; aqui van una a
    -- una para poder comprobar cada existencia. El orden de creacion es el
    -- mismo, de modo que el esquema resultante no cambia.

    -- Deterioro contable de la misma operacion en el corte anterior. Es el T9
    -- del libro, que hoy se digita a mano, calculado por operacion.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'deterioro_mes_anterior') IS NULL
        alter table [det_deterioro_operacion] add [deterioro_mes_anterior] decimal(19, 4) null;

    -- Actual - anterior. Es el gasto contable del periodo por operacion (T10).
    -- Conserva el signo: negativo es recuperacion, no un error.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'variacion_deterioro') IS NULL
        alter table [det_deterioro_operacion] add [variacion_deterioro] decimal(19, 4) null;

    -- ALTA (no estaba en el corte anterior) o VARIACION (estaba en ambos). Las
    -- bajas no tienen fila en el corte actual y por eso no aparecen aqui.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'movimiento') IS NULL
        alter table [det_deterioro_operacion] add [movimiento] nvarchar(12) null;

    -- ------------------------------------------------------------------
    -- 2. Motivo e informativo del cuadre en columnas propias
    -- ------------------------------------------------------------------

    -- Hasta ahora el motivo por el que un control no aplica se concatenaba
    -- dentro de descripcion, que es nvarchar(200). El texto mas largo
    -- (C-VARIACION) llegaba a 182 caracteres: 18 de margen. En SQL Server
    -- pasarse de largo no trunca, lanza error y tumba el INSERT y con el todo
    -- el calculo del corte. Ademas la pantalla volvia a separar la cadena con
    -- una expresion regular, de modo que el dato nacia separado, se pegaba y
    -- se despegaba.

    -- Motivo por el que el control no aplica o la nota del informativo. NULL
    -- cuando el control aplica y cuadra de forma normal.
    IF COL_LENGTH(N'dbo.det_corte_cuadre', N'motivo') IS NULL
        alter table [det_corte_cuadre] add [motivo] nvarchar(200) null;

    -- Controles que se muestran con sus cifras pero nunca son falla. La
    -- columna es NOT NULL con default 0: las filas ya existentes quedan en 0,
    -- que es el valor correcto para todas salvo C-LIBRO, que se recalcula.
    IF COL_LENGTH(N'dbo.det_corte_cuadre', N'informativo') IS NULL
        alter table [det_corte_cuadre] add [informativo] bit not null default '0';

    COMMIT TRANSACTION;
    PRINT 'Fase 4 aplicada sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Fase 4 revertida: no se aplico ningun cambio.';
    THROW;
END CATCH
GO


/*
 * ----------------------------------------------------------------------
 * REVERSION (equivalente a down()). Bloque comentado a proposito.
 * ----------------------------------------------------------------------
 *
 * ATENCION: ademas de soltar las cinco columnas, borra las filas de los dos
 * cuadres de esta fase en det_corte_cuadre (C-MOVIMIENTO y C-VARIACION), que
 * sin sus columnas quedarian huerfanos listandose en pantalla. Los cortes ya
 * calculados pierden esos renglones de forma irreversible y el movimiento del
 * mes hay que recalcularlo.
 *
 * Al soltar motivo e informativo el motivo de los controles que no aplican se
 * pierde: la descripcion ya no lo lleva concatenado. Hay que recalcular los
 * cuadres de los cortes abiertos con la version anterior del motor.
 *
 * informativo llega con una restriccion default, que SQL Server no deja soltar
 * junto con la columna: primero se suelta la restriccion, igual que hace el
 * down() de la migracion.
 *
 * La llave primaria de det_corte_cuadre no se toca: el ancho de codigo lo
 * gobierna la fase 2.
 *
 * NO descomentar: en produccion use rollback/ de migracion-produccion.

BEGIN TRY
    BEGIN TRANSACTION;

    DELETE FROM det_corte_cuadre WHERE codigo IN (N'C-MOVIMIENTO', N'C-VARIACION');

    DECLARE @sql NVARCHAR(MAX) = N'';
    SELECT @sql += N'ALTER TABLE [dbo].[det_corte_cuadre] DROP CONSTRAINT '
                 + OBJECT_NAME([default_object_id]) + N';'
    FROM sys.columns
    WHERE [object_id] = OBJECT_ID(N'[dbo].[det_corte_cuadre]')
      AND [name] IN (N'motivo', N'informativo')
      AND [default_object_id] <> 0;
    EXEC(@sql);

    ALTER TABLE det_corte_cuadre DROP COLUMN motivo, informativo;

    ALTER TABLE det_deterioro_operacion DROP COLUMN
        deterioro_mes_anterior, variacion_deterioro, movimiento;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */
