/*
 * MIGRACION A PRODUCCION - Paso 19
 * Copia de documentacion/credito-fase2-ddl.sql. Lo unico que cambia respecto del original es
 * esta cabecera, el bloque de opciones SET inicial, la guarda de base
 * (ArarFinanciera_PRUEBAS -> ArarFinanciera) y las lineas que indicaban
 * ejecutar sobre pruebas. La logica es identica a la validada en pruebas.
 * Equivale a: migrate 2026_10_07_110000_credito_fase2_registro
 * Prerrequisitos: paso 18. Si hay DocumentoTercero duplicados el indice unico NO se crea (solo PRINT): ver README.
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
 * Credito - Fase 2 (registro del tercero e ingreso de datos)
 * Esquema para ArarFinanciera (produccion).
 *
 * Equivale a `php artisan migrate` de
 * 2026_10_07_110000_credito_fase2_registro.php. Las sentencias DDL reproducen
 * el texto que emite Illuminate\Database\Schema\Grammars\SqlServerGrammar.
 *
 * - Procesos: EmailTratamientoEnviadoA (nvarchar(100) null) y
 *   FechaEmailTratamiento (datetime null). Registran a quien y cuando se envio
 *   el correo de tratamiento de datos. TratamientoDatos no sirve para esto:
 *   es por tercero, Aceptado es NOT NULL y su existencia ya se interpreta como
 *   autorizacion otorgada.
 * - TratamientoDatos: IdProceso (bigint null). Asocia la autorizacion (carga
 *   del formato o aceptacion digital) al proceso. Backfill idempotente de las
 *   filas en NULL: TratamientoDatos no tiene columna de fecha, asi que se
 *   asigna el ultimo proceso del tercero (FechaCreacion, IdProceso). Las filas
 *   de terceros sin procesos quedan en NULL.
 * - Terceros: indice unico terceros_documentotercero_unique sobre
 *   DocumentoTercero. SOLO se crea si no hay documentos duplicados (los NULL
 *   cuentan como un mismo valor). Si los hay, el script no falla: lo informa
 *   con PRINT y deja el indice pendiente hasta depurar
 *   (ver credito-fase2-validacion.md, seccion de duplicados).
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

    IF COL_LENGTH(N'dbo.Procesos', N'EmailTratamientoEnviadoA') IS NULL
        alter table [Procesos] add [EmailTratamientoEnviadoA] nvarchar(100) null;

    IF COL_LENGTH(N'dbo.Procesos', N'FechaEmailTratamiento') IS NULL
        alter table [Procesos] add [FechaEmailTratamiento] datetime null;

    IF COL_LENGTH(N'dbo.TratamientoDatos', N'IdProceso') IS NULL
        alter table [TratamientoDatos] add [IdProceso] bigint null;

    EXEC(N'
        UPDATE td SET IdProceso = (SELECT TOP 1 p.IdProceso FROM Procesos p WHERE p.IdTercero = td.IdTercero ORDER BY p.FechaCreacion DESC, p.IdProceso DESC)
        FROM TratamientoDatos td
        WHERE td.IdProceso IS NULL AND EXISTS (SELECT 1 FROM Procesos p WHERE p.IdTercero = td.IdTercero)');

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'terceros_documentotercero_unique' AND object_id = OBJECT_ID(N'dbo.Terceros'))
    BEGIN
        IF EXISTS (SELECT 1 FROM Terceros GROUP BY DocumentoTercero HAVING COUNT(*) > 1)
            PRINT 'Hay documentos duplicados en Terceros: no se crea terceros_documentotercero_unique. Depurar y volver a ejecutar.';
        ELSE
            create unique index [terceros_documentotercero_unique] on [Terceros] ([DocumentoTercero]);
    END

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

    IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'terceros_documentotercero_unique' AND object_id = OBJECT_ID(N'dbo.Terceros'))
        drop index [terceros_documentotercero_unique] on [Terceros];

    ALTER TABLE Procesos DROP COLUMN EmailTratamientoEnviadoA, FechaEmailTratamiento;
    ALTER TABLE TratamientoDatos DROP COLUMN IdProceso;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */
