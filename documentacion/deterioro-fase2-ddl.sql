/*
 * Deterioro de Cartera - Fase 2 (calculo fiscal)
 * Esquema y semilla parametrica para ArarFinanciera_PRUEBAS.
 *
 * Equivale a `php artisan migrate` de
 * 2026_09_01_100000_create_det_fiscal_tables.php mas la parte fiscal de
 * DeterioroParametrosSeeder. El DDL reproduce literalmente el texto que emite
 * Illuminate\Database\Schema\Grammars\SqlServerGrammar (minusculas, corchetes,
 * modificadores en el orden Increment > Nullable) para que lo validado aqui sea
 * exactamente lo que despues corra en produccion por migracion.
 *
 * La fase 1 se aplico directamente en produccion por no haber alternativa. Esta
 * no: se prueba sobre la copia y por eso el script aborta si lo lanzan contra
 * otra base.
 *
 * Es idempotente y transaccional: se puede repetir sin romper y un fallo a mitad
 * no deja el esquema partido.
 */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'ArarFinanciera_PRUEBAS'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera_PRUEBAS.', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    -- ------------------------------------------------------------------
    -- 1. Parametricas fiscales (RN-07, RN-08, D-04)
    -- ------------------------------------------------------------------

    -- dias_minimos_mora expresa por dias la condicion "clasificacion E o F"
    -- del libro: el rango E arranca en 361.
    IF OBJECT_ID(N'dbo.det_param_fiscal', N'U') IS NULL
    BEGIN
        create table [det_param_fiscal] ([id_param] int identity primary key not null, [metodo] nvarchar(12) not null, [pct_anual] decimal(7, 4) null, [dias_minimos_mora] int not null, [activo] bit not null, [vigente_desde] date not null, [vigente_hasta] date null, [id_usuario] int not null, [fecha_registro] datetime not null);
        create index [ix_param_fiscal_vigencia] on [det_param_fiscal] ([vigente_desde], [vigente_hasta]);
    END

    -- Porcentajes por rango del metodo general (RN-08): 5 %, 10 % y 15 %.
    IF OBJECT_ID(N'dbo.det_param_fiscal_rango', N'U') IS NULL
    BEGIN
        create table [det_param_fiscal_rango] ([id_param] int identity primary key not null, [metodo] nvarchar(12) not null, [rango_codigo] nchar(1) not null, [pct] decimal(7, 4) not null, [vigente_desde] date not null, [vigente_hasta] date null, [id_usuario] int not null, [fecha_registro] datetime not null);
        create index [ix_param_fiscal_rango_vigencia] on [det_param_fiscal_rango] ([vigente_desde], [vigente_hasta]);
    END

    -- ------------------------------------------------------------------
    -- 2. Copia congelada dentro del corte
    -- ------------------------------------------------------------------

    IF OBJECT_ID(N'dbo.det_corte_param_fiscal', N'U') IS NULL
    BEGIN
        create table [det_corte_param_fiscal] ([id_corte] int not null, [metodo] nvarchar(12) not null, [pct_anual] decimal(7, 4) null, [dias_minimos_mora] int not null, [activo] bit not null);
        alter table [det_corte_param_fiscal] add constraint [pk_det_corte_param_fiscal] primary key ([id_corte], [metodo]);
    END

    IF OBJECT_ID(N'dbo.det_corte_param_fiscal_rango', N'U') IS NULL
    BEGIN
        create table [det_corte_param_fiscal_rango] ([id_corte] int not null, [metodo] nvarchar(12) not null, [rango_codigo] nchar(1) not null, [pct] decimal(7, 4) not null);
        alter table [det_corte_param_fiscal_rango] add constraint [pk_det_corte_param_fiscal_rango] primary key ([id_corte], [metodo], [rango_codigo]);
    END

    -- ------------------------------------------------------------------
    -- 3. Resultado fiscal por operacion (RN-07, RN-08, RN-09)
    -- ------------------------------------------------------------------

    -- La migracion agrega las seis columnas en un solo ALTER; aqui van una a
    -- una para poder comprobar cada existencia. El orden de creacion es el
    -- mismo, de modo que el esquema resultante no cambia.
    -- saldo_siesa es la Q del libro: la puebla la fase 6 desde SIESA, hoy queda
    -- nula y el tope se resuelve contra la base.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'saldo_siesa') IS NULL
        alter table [det_deterioro_operacion] add [saldo_siesa] decimal(19, 4) null;

    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'saldo_topado') IS NULL
        alter table [det_deterioro_operacion] add [saldo_topado] decimal(19, 4) null;

    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'fiscal_acumulado_anterior') IS NULL
        alter table [det_deterioro_operacion] add [fiscal_acumulado_anterior] decimal(19, 4) null;

    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'deterioro_fiscal_individual') IS NULL
        alter table [det_deterioro_operacion] add [deterioro_fiscal_individual] decimal(19, 4) null;

    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'deterioro_fiscal_general') IS NULL
        alter table [det_deterioro_operacion] add [deterioro_fiscal_general] decimal(19, 4) null;

    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'deduccion_fiscal_ano') IS NULL
        alter table [det_deterioro_operacion] add [deduccion_fiscal_ano] decimal(19, 4) null;

    -- ------------------------------------------------------------------
    -- 4. Acumulado deducido por operacion y ano gravable
    -- ------------------------------------------------------------------

    -- Reemplaza la hoja `1399 ANO 2025` y es el insumo de P en RN-09. No se
    -- congela por corte: es el historico de lo ya deducido en anos gravables
    -- anteriores.
    IF OBJECT_ID(N'dbo.det_fiscal_acumulado', N'U') IS NULL
    BEGIN
        create table [det_fiscal_acumulado] ([id_operacion] int not null, [ano_gravable] smallint not null, [valor_deducido] decimal(19, 4) not null, [id_corte_origen] int null, [origen] nvarchar(20) not null, [id_usuario] int not null, [fecha_registro] datetime not null);
        alter table [det_fiscal_acumulado] add constraint [pk_det_fiscal_acumulado] primary key ([id_operacion], [ano_gravable]);
    END

    -- ------------------------------------------------------------------
    -- 5. Ampliacion de det_corte_cuadre.codigo
    -- ------------------------------------------------------------------

    -- Los codigos de los cuadres fiscales de RN-12 (C-FISCAL-ACUM,
    -- C-FISCAL-TOPE) no caben en los 10 caracteres de la fase 1. La columna es
    -- parte de la llave primaria y SQL Server no deja alterar el tipo de una
    -- columna indexada, asi que hay que soltar la restriccion y recrearla.
    -- max_length viene en bytes: nvarchar(10) = 20 y nvarchar(20) = 40. Si ya
    -- esta en 40 se omite el paso y la llave no se toca.
    IF EXISTS (
        SELECT 1 FROM sys.columns
        WHERE object_id = OBJECT_ID(N'dbo.det_corte_cuadre')
          AND name = N'codigo' AND max_length < 40
    )
    BEGIN
        ALTER TABLE det_corte_cuadre DROP CONSTRAINT pk_det_corte_cuadre;
        ALTER TABLE det_corte_cuadre ALTER COLUMN codigo nvarchar(20) NOT NULL;
        ALTER TABLE det_corte_cuadre ADD CONSTRAINT pk_det_corte_cuadre PRIMARY KEY (id_corte, codigo);
    END

    -- ------------------------------------------------------------------
    -- 6. Semilla fiscal (DeterioroParametrosSeeder)
    -- ------------------------------------------------------------------

    -- Formato ISO 8601 con T: el login del servidor usa idioma espanol, en el
    -- que un datetime escrito 'aaaa-mm-dd hh:mm:ss' se interpreta como
    -- dd/mm/aaaa y falla. Con la T la lectura es inequivoca. El estilo 126 de
    -- CONVERT es el equivalente exacto del date('Y-m-d\TH:i:s') del seeder.
    DECLARE @fecha_registro nvarchar(19) = CONVERT(nvarchar(19), GETDATE(), 126);
    DECLARE @vigente_desde date = '2016-01-01';
    DECLARE @id_usuario int = 0;

    -- Mismo criterio de idempotencia del seeder: solo si la tabla esta vacia.
    -- Individual del 33 % anual como metodo adoptado (D-04); el general queda
    -- cargado pero desactivado.
    IF NOT EXISTS (SELECT 1 FROM det_param_fiscal)
    BEGIN
        INSERT INTO det_param_fiscal
            (metodo, pct_anual, dias_minimos_mora, activo, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'INDIVIDUAL', 0.3300, 361, 1, @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'GENERAL',    NULL,    91, 0, @vigente_desde, NULL, @id_usuario, @fecha_registro);
    END

    -- 5 % (C), 10 % (D), 15 % (E y F).
    IF NOT EXISTS (SELECT 1 FROM det_param_fiscal_rango)
    BEGIN
        INSERT INTO det_param_fiscal_rango
            (metodo, rango_codigo, pct, vigente_desde, vigente_hasta, id_usuario, fecha_registro)
        VALUES
            (N'GENERAL', N'C', 0.0500, @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'GENERAL', N'D', 0.1000, @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'GENERAL', N'E', 0.1500, @vigente_desde, NULL, @id_usuario, @fecha_registro),
            (N'GENERAL', N'F', 0.1500, @vigente_desde, NULL, @id_usuario, @fecha_registro);
    END

    COMMIT TRANSACTION;
    PRINT 'Fase 2 aplicada sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Fase 2 revertida: no se aplico ningun cambio.';
    THROW;
END CATCH
GO


/*
 * ----------------------------------------------------------------------
 * REVERSION (equivalente a down()). Bloque comentado a proposito.
 * ----------------------------------------------------------------------
 *
 * ATENCION: ademas de deshacer el esquema, borra las filas de los tres cuadres
 * fiscales de det_corte_cuadre (C-FISCAL, C-FISCAL-ACUM y C-FISCAL-TOPE).
 * C-FISCAL tambien se va porque es un control de esta fase y sin sus columnas
 * quedaria huerfano listandose en pantalla tras el rollback. Los cortes ya
 * calculados pierden esos renglones de cuadre de forma irreversible.
 *
 * Las parametricas sembradas en el paso 6 desaparecen con sus tablas.
 *
 * Descomentar y ejecutar solo sobre ArarFinanciera_PRUEBAS.

BEGIN TRY
    BEGIN TRANSACTION;

    DELETE FROM det_corte_cuadre WHERE codigo IN (N'C-FISCAL', N'C-FISCAL-ACUM', N'C-FISCAL-TOPE');

    -- codigo vuelve a 10: misma razon que en el paso 5, hay que soltar y
    -- recrear la llave primaria para poder cambiar el tipo.
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

 */
