/*
 * MIGRACION A PRODUCCION - Paso 21
 * Copia de documentacion/credito-fase4-ddl.sql. Lo unico que cambia respecto del original es
 * esta cabecera, el bloque de opciones SET inicial, la guarda de base
 * (ArarFinanciera_PRUEBAS -> ArarFinanciera) y las lineas que indicaban
 * ejecutar sobre pruebas. La logica es identica a la validada en pruebas.
 * Equivale a: migrate 2026_10_07_130000_credito_fase4_centrales
 * Prerrequisitos: paso 20. FKs a Procesos(IdProceso) y Terceros(IdTercero).
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
 * Credito - Fase 4 (centrales de riesgo con varios proveedores)
 * Esquema para ArarFinanciera (produccion).
 *
 * Equivale a `php artisan migrate` de
 * 2026_10_07_130000_credito_fase4_centrales.php. Las sentencias DDL reproducen
 * el texto que emite Illuminate\Database\Schema\Grammars\SqlServerGrammar.
 *
 * - ConsultasCentrales: una fila por consulta a un proveedor (exitosa o no).
 *   ResumenJson guarda el resumen normalizado; RespuestaCruda el XML/JSON del
 *   proveedor (no se devuelve al navegador). VigenteHasta = FechaConsulta +
 *   vigencia en dias vigente al momento de consultar (NULL si fallo).
 *   Indice (IdTercero, Proveedor, FechaConsulta) para buscar la consulta vigente.
 * - ConfiguracionCentrales: ajustes editables desde Administracion del sitio
 *   (clave/valor). Seed: predeterminado=transunion, vigenciaDias=30,
 *   habilitado.transunion=1, habilitado.datacredito=1. Los cambios se auditan
 *   en ConfiguracionAuditoria (Tabla='ConfiguracionCentrales', Campo=Clave).
 *   Las credenciales NO se guardan en BD: van en .env (config/centrales.php).
 *
 * SQL Server 2014: sin CREATE OR ALTER ni DROP ... IF EXISTS.
 *
 * Es idempotente y transaccional.
 */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'ArarFinanciera'
BEGIN
    THROW 50000, 'Este script solo debe ejecutarse sobre ArarFinanciera (produccion).', 1;
END

BEGIN TRY
    BEGIN TRANSACTION;

    IF OBJECT_ID(N'dbo.ConsultasCentrales', N'U') IS NULL
    BEGIN
        create table [ConsultasCentrales] ([Id] bigint identity primary key not null, [IdProceso] bigint null, [IdTercero] bigint not null, [Proveedor] nvarchar(30) not null, [Ambiente] nvarchar(20) null, [Simulado] bit not null default '0', [FechaConsulta] datetime not null default getdate(), [VigenteHasta] datetime null, [IdUsuario] bigint null, [Exitosa] bit not null default '0', [MensajeError] nvarchar(500) null, [ResumenJson] nvarchar(max) null, [RespuestaCruda] nvarchar(max) null);
        alter table [ConsultasCentrales] add constraint [consultascentrales_idproceso_foreign] foreign key ([IdProceso]) references [Procesos] ([IdProceso]);
        alter table [ConsultasCentrales] add constraint [consultascentrales_idtercero_foreign] foreign key ([IdTercero]) references [Terceros] ([IdTercero]);
        create index [consultascentrales_idtercero_proveedor_fechaconsulta_index] on [ConsultasCentrales] ([IdTercero], [Proveedor], [FechaConsulta]);
        create index [consultascentrales_idproceso_index] on [ConsultasCentrales] ([IdProceso]);
    END

    IF OBJECT_ID(N'dbo.ConfiguracionCentrales', N'U') IS NULL
    BEGIN
        create table [ConfiguracionCentrales] ([IdConfiguracion] int identity primary key not null, [Clave] nvarchar(50) not null, [Valor] nvarchar(100) not null, [IdUsuario] bigint null, [updated_at] datetime null);
        create unique index [configuracioncentrales_clave_unique] on [ConfiguracionCentrales] ([Clave]);
    END

    EXEC(N'
        INSERT INTO ConfiguracionCentrales (Clave, Valor)
        SELECT v.Clave, v.Valor
        FROM (VALUES (N''predeterminado'', N''transunion''), (N''vigenciaDias'', N''30''), (N''habilitado.transunion'', N''1''), (N''habilitado.datacredito'', N''1'')) v (Clave, Valor)
        WHERE NOT EXISTS (SELECT 1 FROM ConfiguracionCentrales c WHERE c.Clave = v.Clave)');

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

/*
 * Reversion (equivale al down() de la migracion).
 * NO descomentar: en produccion use rollback/ de migracion-produccion.

BEGIN TRY
    BEGIN TRANSACTION;

    DROP TABLE ConfiguracionCentrales;
    DROP TABLE ConsultasCentrales;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */
