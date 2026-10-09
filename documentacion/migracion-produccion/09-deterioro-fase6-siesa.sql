/*
 * MIGRACION A PRODUCCION - Paso 09
 * Copia de documentacion/deterioro-fase6-ddl.sql. Lo unico que cambia respecto del original es
 * esta cabecera, el bloque de opciones SET inicial, la guarda de base
 * (ArarFinanciera_PRUEBAS -> ArarFinanciera) y las lineas que indicaban
 * ejecutar sobre pruebas. La logica es identica a la validada en pruebas.
 * Equivale a: migrate 2026_09_04_100000_create_det_siesa_tables
 * Prerrequisitos: paso 08.
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
 * Deterioro de Cartera - Fase 6a (conexion a SIESA, tope fiscal y conciliacion
 * C-3: esquema)
 * Esquema para ArarFinanciera (produccion).
 *
 * Equivale a `php artisan migrate` de
 * 2026_09_04_100000_create_det_siesa_tables.php. El DDL reproduce el texto que
 * emite Illuminate\Database\Schema\Grammars\SqlServerGrammar (minusculas,
 * identificadores delimitados, modificadores en el orden Increment > Nullable >
 * Default) para que lo validado aqui sea exactamente lo que despues corra en
 * produccion por migracion.
 *
 * ORDEN DE DESPLIEGUE: fase 2 -> el 1399 (det_fiscal_acumulado) -> fase 3 ->
 * fase 4 -> fase 5a -> fase 6a.
 *
 * Esta fase NO despliega "Ajustes y notas" (det_ajuste_manual,
 * det_nota_cartera): quedan para una entrega posterior.
 *
 * det_corte_saldo_siesa es un snapshot congelado, no una consulta en vivo: se
 * guarda con el corte para que la conciliacion y el tope fiscal de RN-09 sigan
 * dando el mismo numero dentro de dos anios aunque SIESA se haya movido.
 * Guarda tambien las filas que no cruzan contra ninguna operacion del corte,
 * porque C-3 necesita los dos sentidos.
 *
 * det_conciliacion_partida se regenera entera en cada ejecucion del corte,
 * igual que det_deterioro_operacion, y por eso limpiarCorte() la borra. La
 * explicacion del usuario vive en det_conciliacion_explicacion, en tabla
 * aparte y con llave (id_corte, numero_operacion): si viviera junto a la
 * partida, cuya llave es un identity que cambia con cada recalculo, el trabajo
 * del usuario se perderia en cada corrida.
 *
 * origen_base es el alcance por operacion que D-15 exige. El parametro
 * siesa_manda_sobre_base de la fase 1 sigue siendo el interruptor global por
 * corte y no alcanza: en el mismo corte conviven operaciones con base de
 * factoring y operaciones con base de SIESA. El default FACTORING deja los
 * cortes ya calculados con el valor que les corresponde.
 *
 * Sobre SIESA (UNOEEARAR) este script no hace absolutamente nada: la conexion
 * es de solo lectura (D-13) y el modulo unicamente le hace SELECT desde el
 * motor.
 *
 * Es idempotente y transaccional: se puede repetir sin romper y un fallo a
 * mitad no deja el esquema partido.
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
    -- 1. Snapshot de la cartera abierta de SIESA a la fecha del corte
    -- ------------------------------------------------------------------

    -- Una fila por (tipo de documento de cruce, consecutivo, tercero) con
    -- saldo neto distinto de cero, mas todos los OPE aunque queden en cero:
    -- un OPE cancelado en SIESA que todavia tiene capital en factoring es una
    -- partida de conciliacion con saldo cero, no una operacion ausente de
    -- SIESA, y distinguir las dos cosas es justo lo que el usuario explica.
    --
    -- tipo_docto_cruce admite nulo: 11.070 filas de la compania 7 son saldo
    -- abierto sin documento de cruce, y sin ellas el snapshot no reproduce el
    -- total de SIESA porque son el lado credito de la cartera.
    --
    -- nit y razon_social se dimensionan como en UNOEEARAR.dbo.t200_mm_terceros
    -- (varchar 25 y varchar 100), medido el 2026-09-04.
    IF OBJECT_ID(N'dbo.det_corte_saldo_siesa', N'U') IS NULL
    BEGIN
        create table [det_corte_saldo_siesa] ([id_saldo] int identity primary key not null, [id_corte] int not null, [tipo_docto_cruce] nchar(3) null, [consec_docto_cruce] int not null, [nit] nvarchar(25) null, [razon_social] nvarchar(100) null, [saldo] decimal(19, 4) not null, [fecha_extraccion] datetime not null);
        create index [ix_det_saldo_siesa_corte] on [det_corte_saldo_siesa] ([id_corte]);
        create index [ix_det_saldo_siesa_cruce] on [det_corte_saldo_siesa] ([id_corte], [tipo_docto_cruce], [consec_docto_cruce]);
    END

    -- ------------------------------------------------------------------
    -- 2. Partidas de la conciliacion C-3. Se regeneran en cada corrida.
    -- ------------------------------------------------------------------

    -- id_operacion queda nulo en las partidas SOLO_SIESA, que por definicion
    -- no tienen operacion en el corte. numero_operacion siempre viene: es el
    -- consecutivo del OPE o el numero de la operacion de factoring, y es la
    -- mitad de la llave con la que la explicacion sobrevive al recalculo.
    IF OBJECT_ID(N'dbo.det_conciliacion_partida', N'U') IS NULL
    BEGIN
        create table [det_conciliacion_partida] ([id_partida] int identity primary key not null, [id_corte] int not null, [id_operacion] int null, [numero_operacion] int not null, [nit] nvarchar(25) null, [cliente] nvarchar(255) null, [saldo_siesa] decimal(19, 4) null, [saldo_factoring] decimal(19, 4) null, [diferencia] decimal(19, 4) not null, [tipo] nvarchar(20) not null);
        create index [ix_det_concilia_corte] on [det_conciliacion_partida] ([id_corte]);
        create index [ix_det_concilia_operacion] on [det_conciliacion_partida] ([id_corte], [numero_operacion]);
    END

    -- ------------------------------------------------------------------
    -- 3. Explicacion del usuario. Fuera de la tabla de partidas a proposito.
    -- ------------------------------------------------------------------

    -- PENDIENTE no se guarda: es la ausencia de fila, de modo que una partida
    -- que aparece por primera vez tras un recalculo nace pendiente sin que
    -- nadie tenga que escribirla.
    IF OBJECT_ID(N'dbo.det_conciliacion_explicacion', N'U') IS NULL
    BEGIN
        create table [det_conciliacion_explicacion] ([id_corte] int not null, [numero_operacion] int not null, [estado] nvarchar(20) not null, [explicacion] nvarchar(500) not null, [id_usuario] int not null, [fecha] datetime not null);
        alter table [det_conciliacion_explicacion] add constraint [pk_det_concilia_explicacion] primary key ([id_corte], [numero_operacion]);
    END

    -- ------------------------------------------------------------------
    -- 4. Origen de la base por operacion (D-15)
    -- ------------------------------------------------------------------

    -- FACTORING (capital vencido + interes congelado) o SIESA (saldo de SIESA
    -- + interes congelado). El default backfilea los cortes ya calculados con
    -- FACTORING, que es de donde salio su base.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'origen_base') IS NULL
        alter table [det_deterioro_operacion] add [origen_base] nvarchar(10) not null default 'FACTORING';

    COMMIT TRANSACTION;
    PRINT 'Fase 6a aplicada sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Fase 6a revertida: no se aplico ningun cambio.';
    THROW;
END CATCH
GO


/*
 * ----------------------------------------------------------------------
 * REVERSION (equivalente a down()). Bloque comentado a proposito.
 * ----------------------------------------------------------------------
 *
 * ATENCION: ademas de deshacer el esquema, borra las filas de los controles
 * C-SIESA-BASE, C-SIESA-EXTRAC, C-CONCILIA y C-MARCAS en det_corte_cuadre. Los
 * cortes ya calculados pierden esos cuatro renglones de cuadre de forma
 * irreversible. C-MARCAS no depende de ninguna columna nueva, pero lo emite el
 * motor de esta fase y sin el codigo quedaria huerfano.
 *
 * ATENCION: al soltar det_conciliacion_explicacion se pierden las
 * explicaciones capturadas por los usuarios, que es el unico dato de esta fase
 * que no se puede recalcular.
 *
 * origen_base lleva restriccion de default, que hay que soltar antes de la
 * columna: es lo mismo que emite el dropColumn de la migracion.
 *
 * NO descomentar: en produccion use rollback/ de migracion-produccion.

BEGIN TRY
    BEGIN TRANSACTION;

    DELETE FROM det_corte_cuadre
    WHERE codigo IN (N'C-SIESA-BASE', N'C-SIESA-EXTRAC', N'C-CONCILIA', N'C-MARCAS');

    DECLARE @sql NVARCHAR(MAX) = '';
    SELECT @sql += 'ALTER TABLE [dbo].[det_deterioro_operacion] DROP CONSTRAINT '
                 + OBJECT_NAME([default_object_id]) + ';'
    FROM sys.columns
    WHERE [object_id] = OBJECT_ID('[dbo].[det_deterioro_operacion]')
      AND [name] IN ('origen_base') AND [default_object_id] <> 0;
    EXEC(@sql);
    ALTER TABLE det_deterioro_operacion DROP COLUMN origen_base;

    DROP TABLE det_conciliacion_explicacion;
    DROP TABLE det_conciliacion_partida;
    DROP TABLE det_corte_saldo_siesa;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */
