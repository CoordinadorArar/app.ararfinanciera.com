/*
 * MIGRACION A PRODUCCION - Paso 06
 * Copia de documentacion/deterioro-fase5-ddl.sql. Lo unico que cambia respecto del original es
 * esta cabecera, el bloque de opciones SET inicial, la guarda de base
 * (ArarFinanciera_PRUEBAS -> ArarFinanciera) y las lineas que indicaban
 * ejecutar sobre pruebas. La logica es identica a la validada en pruebas.
 * Equivale a: migrate 2026_09_03_110000_create_det_suspension_tables
 * Prerrequisitos: paso 05. Crea un indice unico FILTRADO: exige QUOTED_IDENTIFIER y ANSI_NULLS en ON (bloque SET de abajo; con sqlcmd use ademas -I).
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
 * Deterioro de Cartera - Fase 5a (suspension de causacion de intereses: esquema,
 * parametrica y motor)
 * Esquema para ArarFinanciera (produccion).
 *
 * Equivale a `php artisan migrate` de
 * 2026_09_03_110000_create_det_suspension_tables.php. El DDL reproduce
 * literalmente el texto que emite Illuminate\Database\Schema\Grammars\
 * SqlServerGrammar (minusculas, corchetes, modificadores en el orden
 * Increment > Nullable > Default) para que lo validado aqui sea exactamente lo
 * que despues corra en produccion por migracion.
 *
 * ORDEN DE DESPLIEGUE: fase 2 -> el 1399 (det_fiscal_acumulado) -> fase 3 ->
 * fase 4 -> fase 5a.
 *
 * La marca de suspension (det_suspension_interes) NO es una tabla de corte: se
 * registra y se levanta en cualquier momento, fuera del snapshot. El motor la
 * aplica a cada corte comparando fecha_evento y fecha_reactivacion contra la
 * fecha del corte, de modo que levantar una marca hoy no cambia un corte de
 * hace tres meses.
 *
 * interes_vencido y base_deterioro de det_deterioro_operacion NO se tocan: son
 * el insumo de los controles C-INTERES y C-BASE contra el detalle de cuotas de
 * origen (RN-12). Si el congelamiento las sobreescribiera esos dos controles
 * quedarian ciegos a un descuadre real de la extraccion, que es lo que
 * vigilan. El congelamiento vive en columnas nuevas
 * (interes_vencido_congelado, base_congelada, interes_no_facturado) que el
 * motor consume aparte.
 *
 * Esta fase NO implementa el cargue inicial (D-14, origen = CARGUE_INICIAL) ni
 * la escritura de la marca en el sistema de factoring (D-12,
 * marca_factoring_aplicada / valor_anterior_factoria / fecha_escritura): esas
 * tres columnas quedan creadas y sin uso.
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
    -- 1. Causales de suspension (D-05), con vigencia
    -- ------------------------------------------------------------------

    IF OBJECT_ID(N'dbo.det_param_causal_suspension', N'U') IS NULL
    BEGIN
        create table [det_param_causal_suspension] ([id_param] int identity primary key not null, [codigo] nvarchar(20) not null, [descripcion] nvarchar(120) not null, [activa] bit not null, [vigente_desde] date not null, [vigente_hasta] date null, [id_usuario] int not null, [fecha_registro] datetime not null);
        create index [ix_param_causal_susp_vigencia] on [det_param_causal_suspension] ([vigente_desde], [vigente_hasta]);
    END

    -- ------------------------------------------------------------------
    -- 2. Copia congelada dentro del corte
    -- ------------------------------------------------------------------

    IF OBJECT_ID(N'dbo.det_corte_param_causal_suspension', N'U') IS NULL
    BEGIN
        create table [det_corte_param_causal_suspension] ([id_corte] int not null, [codigo] nvarchar(20) not null, [descripcion] nvarchar(120) not null, [activa] bit not null);
        alter table [det_corte_param_causal_suspension] add constraint [pk_det_corte_param_causal_susp] primary key ([id_corte], [codigo]);
    END

    -- ------------------------------------------------------------------
    -- 3. Marcas de suspension (D-05, D-06). Fuera del snapshot del corte.
    -- ------------------------------------------------------------------

    IF OBJECT_ID(N'dbo.det_suspension_interes', N'U') IS NULL
    BEGIN
        create table [det_suspension_interes] ([id_suspension] int identity primary key not null, [id_operacion] int not null, [causal_codigo] nvarchar(20) not null, [fecha_evento] date not null, [observacion] nvarchar(500) not null, [soporte] nvarchar(255) null, [origen] nvarchar(20) not null default 'MANUAL', [existe_en_factoring] bit not null default '1', [interes_congelado] decimal(19, 4) null, [id_corte_congelado] int null, [marca_factoring_aplicada] bit not null default '0', [valor_anterior_factoring] decimal(19, 4) null, [fecha_escritura] datetime null, [id_usuario] int not null, [fecha_registro] datetime not null, [fecha_reactivacion] datetime null, [id_usuario_reactivacion] int null, [observacion_reactivacion] nvarchar(500) null);
        create index [ix_det_suspension_operacion] on [det_suspension_interes] ([id_operacion]);
    END

    -- Una sola marca activa por operacion. Es un indice unico filtrado, que
    -- Laravel no expresa por Blueprint y se crea a mano tanto aqui como en la
    -- migracion (con DB::statement).
    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes
        WHERE object_id = OBJECT_ID(N'dbo.det_suspension_interes')
          AND name = N'ux_det_suspension_operacion_activa'
    )
    BEGIN
        CREATE UNIQUE INDEX ux_det_suspension_operacion_activa
            ON det_suspension_interes (id_operacion)
            WHERE fecha_reactivacion IS NULL;
    END

    -- ------------------------------------------------------------------
    -- 4. Congelamiento por operacion (D-06)
    -- ------------------------------------------------------------------

    -- La migracion agrega las cinco columnas en un solo ALTER; aqui van una a
    -- una para poder comprobar cada existencia. El orden de creacion es el
    -- mismo, de modo que el esquema resultante no cambia.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'suspendida') IS NULL
        alter table [det_deterioro_operacion] add [suspendida] bit not null default '0';

    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'id_suspension') IS NULL
        alter table [det_deterioro_operacion] add [id_suspension] int null;

    -- Interes congelado que aplica a este corte.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'interes_vencido_congelado') IS NULL
        alter table [det_deterioro_operacion] add [interes_vencido_congelado] decimal(19, 4) null;

    -- capital_vencido + interes_vencido_congelado. Nulo cuando la operacion no
    -- esta suspendida: ese nulo hace que el motor use la base normal.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'base_congelada') IS NULL
        alter table [det_deterioro_operacion] add [base_congelada] decimal(19, 4) null;

    -- Lo que FACTORING siguio calculando internamente y no se facturo.
    IF COL_LENGTH(N'dbo.det_deterioro_operacion', N'interes_no_facturado') IS NULL
        alter table [det_deterioro_operacion] add [interes_no_facturado] decimal(19, 4) null;

    COMMIT TRANSACTION;
    PRINT 'Fase 5a aplicada sobre ' + DB_NAME() + '.';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;

    PRINT 'Fase 5a revertida: no se aplico ningun cambio.';
    THROW;
END CATCH
GO


/*
 * ----------------------------------------------------------------------
 * REVERSION (equivalente a down()). Bloque comentado a proposito.
 * ----------------------------------------------------------------------
 *
 * ATENCION: ademas de deshacer el esquema, borra la fila del control
 * C-SUSPENSION en det_corte_cuadre. Los cortes ya calculados pierden ese
 * renglon de cuadre de forma irreversible.
 *
 * El indice filtrado se suelta antes de la tabla, igual que en la migracion.
 *
 * NO descomentar: en produccion use rollback/ de migracion-produccion.

BEGIN TRY
    BEGIN TRANSACTION;

    DELETE FROM det_corte_cuadre WHERE codigo = N'C-SUSPENSION';

    ALTER TABLE det_deterioro_operacion DROP COLUMN
        suspendida, id_suspension, interes_vencido_congelado,
        base_congelada, interes_no_facturado;

    DROP INDEX ux_det_suspension_operacion_activa ON det_suspension_interes;
    DROP TABLE det_suspension_interes;
    DROP TABLE det_corte_param_causal_suspension;
    DROP TABLE det_param_causal_suspension;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */
