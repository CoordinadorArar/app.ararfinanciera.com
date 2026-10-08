/*
 * Credito - Fase 1 (motor de calculo y configuracion por pagaduria)
 * Esquema para ArarFinanciera_PRUEBAS.
 *
 * Equivale a `php artisan migrate` de
 * 2026_10_07_100000_credito_fase1_pagadurias.php. Las sentencias DDL reproducen
 * el texto que emite Illuminate\Database\Schema\Grammars\SqlServerGrammar.
 *
 * - Pagadurias: EstadoPagaduria (bit, default 1; no existia en la base aunque la
 *   migracion de 2022 la declaraba), UsaReglaSMMLV (bit, default 0) y
 *   UmbralSMMLV (decimal(5,2), default 2). Seed: UsaReglaSMMLV = 1 para 6 y 8,
 *   que eran los IDs fijos de ProcesosController, solo en la ejecucion que crea
 *   la columna (repetir el script no pisa lo configurado en la pantalla).
 * - Las reglas de edad solo se siembran en pagadurias que no tienen ninguna.
 * - PagaduriasReglasEdad: plazo maximo y % de seguro mensual (fraccion sobre el
 *   monto) por rango de edad y pagaduria. Seed para todas las pagadurias:
 *   18-70 -> 120 meses y 0.003; 71-74 -> 48 meses y 0.003; 75-99 -> 48 meses y
 *   0.005625. Unifica la regla de registro (120/48 desde 71 anos) con la de
 *   seguro (0.005625 desde 75). El simulador usaba 96/60: negocio debe revisarlo.
 * - ConfiguracionAuditoria: bitacora de cambios de configuracion.
 *
 * Convencion de CuposConfigCalculos.TipoDescuentoMaximo (confirmada en el
 * codigo previo y en las formulas): '%' = ingresos > UmbralSMMLV x SMMLV
 * (descuento maximo como porcentaje, formulas "/ 2"); '$' = ingresos <=
 * UmbralSMMLV x SMMLV (formulas que restan salarioMinimoMensual). Si la
 * pagaduria no usa la regla, se toma su primera formula no vacia.
 *
 * SQL Server 2014: sin CREATE OR ALTER ni JSON. Los seeds que referencian
 * columnas agregadas en el mismo lote van por EXEC para evitar el error de
 * compilacion "Invalid column name".
 *
 * Es idempotente y transaccional.
 */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'ArarFinanciera_PRUEBAS'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera_PRUEBAS.', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    IF COL_LENGTH(N'dbo.Pagadurias', N'EstadoPagaduria') IS NULL
        alter table [Pagadurias] add [EstadoPagaduria] bit not null default '1';

    DECLARE @reglaCreada BIT = 0;

    IF COL_LENGTH(N'dbo.Pagadurias', N'UsaReglaSMMLV') IS NULL
    BEGIN
        alter table [Pagadurias] add [UsaReglaSMMLV] bit not null default '0';
        SET @reglaCreada = 1;
    END

    IF COL_LENGTH(N'dbo.Pagadurias', N'UmbralSMMLV') IS NULL
        alter table [Pagadurias] add [UmbralSMMLV] decimal(5, 2) not null default '2';

    IF @reglaCreada = 1
        EXEC(N'update [Pagadurias] set [UsaReglaSMMLV] = 1 where [IdPagaduria] in (6, 8)');

    IF OBJECT_ID(N'dbo.PagaduriasReglasEdad', N'U') IS NULL
    BEGIN
        create table [PagaduriasReglasEdad] ([IdReglaEdad] bigint identity primary key not null, [IdPagaduria] bigint not null, [EdadMin] int not null, [EdadMax] int not null, [PlazoMaximo] int not null, [PorcentajeSeguro] decimal(9, 6) not null, [created_at] datetime null, [updated_at] datetime null);
        alter table [PagaduriasReglasEdad] add constraint [pagaduriasreglasedad_idpagaduria_foreign] foreign key ([IdPagaduria]) references [Pagadurias] ([IdPagaduria]);
    END

    EXEC(N'
        INSERT INTO PagaduriasReglasEdad (IdPagaduria, EdadMin, EdadMax, PlazoMaximo, PorcentajeSeguro, created_at, updated_at)
        SELECT p.IdPagaduria, v.EdadMin, v.EdadMax, v.PlazoMaximo, v.PorcentajeSeguro, GETDATE(), GETDATE()
        FROM Pagadurias p
        CROSS JOIN (VALUES (18, 70, 120, 0.003000), (71, 74, 48, 0.003000), (75, 99, 48, 0.005625)) v (EdadMin, EdadMax, PlazoMaximo, PorcentajeSeguro)
        WHERE NOT EXISTS (SELECT 1 FROM PagaduriasReglasEdad r WHERE r.IdPagaduria = p.IdPagaduria)');

    IF OBJECT_ID(N'dbo.ConfiguracionAuditoria', N'U') IS NULL
    BEGIN
        create table [ConfiguracionAuditoria] ([Id] bigint identity primary key not null, [Tabla] nvarchar(100) not null, [IdRegistro] bigint not null, [Campo] nvarchar(100) not null, [ValorAnterior] nvarchar(max) null, [ValorNuevo] nvarchar(max) null, [IdUsuario] bigint null, [Fecha] datetime not null default getdate());
        create index [configuracionauditoria_tabla_idregistro_index] on [ConfiguracionAuditoria] ([Tabla], [IdRegistro]);
    END

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

/*
 * Reversion (equivale al down() de la migracion). EstadoPagaduria no se
 * elimina porque la migracion de 2022 ya la declaraba.
 * Descomentar y ejecutar solo sobre ArarFinanciera_PRUEBAS.

BEGIN TRY
    BEGIN TRANSACTION;

    DROP TABLE ConfiguracionAuditoria;
    DROP TABLE PagaduriasReglasEdad;

    DECLARE @sql NVARCHAR(MAX) = N'';
    SELECT @sql += N'ALTER TABLE [dbo].[Pagadurias] DROP CONSTRAINT '
                 + OBJECT_NAME([default_object_id]) + N';'
    FROM sys.columns
    WHERE [object_id] = OBJECT_ID(N'[dbo].[Pagadurias]')
      AND [name] IN (N'UsaReglaSMMLV', N'UmbralSMMLV')
      AND [default_object_id] <> 0;
    EXEC(@sql);

    ALTER TABLE Pagadurias DROP COLUMN UsaReglaSMMLV, UmbralSMMLV;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */
