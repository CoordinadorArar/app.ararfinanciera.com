/*
 * MIGRACION A PRODUCCION - Paso 20
 * Copia de documentacion/credito-fase3-ddl.sql. Lo unico que cambia respecto del original es
 * esta cabecera, el bloque de opciones SET inicial, la guarda de base
 * (ArarFinanciera_PRUEBAS -> ArarFinanciera) y las lineas que indicaban
 * ejecutar sobre pruebas. La logica es identica a la validada en pruebas.
 * Equivale a: migrate 2026_10_07_120000_credito_fase3_procesos
 * Prerrequisitos: paso 19 (usa TratamientoDatos.IdProceso). FKs a Procesos(IdProceso) y DocumentosSolicitados(IdDocumentoSolicitado).
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
 * Credito - Fase 3 (gestion de procesos de credito)
 * Esquema para ArarFinanciera (produccion).
 *
 * Equivale a `php artisan migrate` de
 * 2026_10_07_120000_credito_fase3_procesos.php. Las sentencias DDL reproducen
 * el texto que emite Illuminate\Database\Schema\Grammars\SqlServerGrammar.
 *
 * - MotivosRechazo: catalogo de motivos de rechazo. Seed con los 6 motivos que
 *   tenia el select oculto de procesos/aprobar-credito.blade.php (no existia
 *   tabla). El 1 ("No tiene cupo") se usa para el rechazo automatico sin cupo.
 * - ProcesosHistorial: una fila por cambio de estado (EstadoAnterior NULL =
 *   creacion). Backfill: una fila por proceso existente sin historial, con su
 *   estado actual, Procesos.IdUsuario, FechaCreacion y la observacion
 *   "Estado inicial (migración)".
 * - ProcesosDocumentos: estado de cada documento de soporte por proceso
 *   (cargado/aprobado/rechazado), con indice unico (IdProceso, IdDocumento).
 *   Backfill desde los CSV Procesos.DocumentosCargados/DocumentosAprobados
 *   (sin STRING_SPLIT: busqueda de ',id,' en ',' + CSV, que ignora repetidos)
 *   y del documento 2 desde TratamientoDatos.IdProceso cuando hay archivo.
 *   Ruta del documento 2: app/public/tratamiento_datos/<archivo de RutaFormato>.
 *   Resto: la que armaba el codigo anterior,
 *   app/public/documentos-soporte-<doc>-<proceso>/<doc>-<proceso>-<NombreDocumento>.pdf.
 *
 * SQL Server 2014: sin CREATE OR ALTER, STRING_SPLIT ni DROP ... IF EXISTS.
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

    IF OBJECT_ID(N'dbo.MotivosRechazo', N'U') IS NULL
        create table [MotivosRechazo] ([IdMotivo] int not null, [NombreMotivo] nvarchar(150) not null, [EstadoMotivo] bit not null default '1', primary key ([IdMotivo]));

    EXEC(N'
        INSERT INTO MotivosRechazo (IdMotivo, NombreMotivo, EstadoMotivo)
        SELECT v.IdMotivo, v.NombreMotivo, 1
        FROM (VALUES (1, N''No tiene cupo''), (2, N''Mal hábito de pago''), (3, N''Compra cartera en proceso''),
                     (4, N''No cumple política de antigüedad''), (5, N''Proceso jurídico en curso''), (6, N''No cumple política compra de cartera'')) v (IdMotivo, NombreMotivo)
        WHERE NOT EXISTS (SELECT 1 FROM MotivosRechazo m WHERE m.IdMotivo = v.IdMotivo)');

    IF OBJECT_ID(N'dbo.ProcesosHistorial', N'U') IS NULL
    BEGIN
        create table [ProcesosHistorial] ([IdHistorial] bigint identity primary key not null, [IdProceso] bigint not null, [EstadoAnterior] int null, [EstadoNuevo] int not null, [IdMotivo] int null, [Observacion] nvarchar(500) null, [IdUsuario] bigint null, [Fecha] datetime not null default getdate());
        alter table [ProcesosHistorial] add constraint [procesoshistorial_idproceso_foreign] foreign key ([IdProceso]) references [Procesos] ([IdProceso]);
        alter table [ProcesosHistorial] add constraint [procesoshistorial_idmotivo_foreign] foreign key ([IdMotivo]) references [MotivosRechazo] ([IdMotivo]);
        create index [procesoshistorial_idproceso_index] on [ProcesosHistorial] ([IdProceso]);
    END

    EXEC(N'
        INSERT INTO ProcesosHistorial (IdProceso, EstadoAnterior, EstadoNuevo, IdMotivo, Observacion, IdUsuario, Fecha)
        SELECT p.IdProceso, NULL, p.EstadoProceso, NULL, N''Estado inicial (migración)'', p.IdUsuario, ISNULL(p.FechaCreacion, GETDATE())
        FROM Procesos p
        WHERE p.EstadoProceso IS NOT NULL AND NOT EXISTS (SELECT 1 FROM ProcesosHistorial h WHERE h.IdProceso = p.IdProceso)');

    IF OBJECT_ID(N'dbo.ProcesosDocumentos', N'U') IS NULL
    BEGIN
        create table [ProcesosDocumentos] ([IdProcesoDocumento] bigint identity primary key not null, [IdProceso] bigint not null, [IdDocumento] bigint not null, [Estado] nvarchar(20) not null, [Ruta] nvarchar(255) null, [NombreArchivo] nvarchar(150) null, [IdMotivo] int null, [Observacion] nvarchar(500) null, [IdUsuario] bigint null, [Fecha] datetime not null default getdate(), [updated_at] datetime null);
        alter table [ProcesosDocumentos] add constraint [procesosdocumentos_idproceso_foreign] foreign key ([IdProceso]) references [Procesos] ([IdProceso]);
        alter table [ProcesosDocumentos] add constraint [procesosdocumentos_iddocumento_foreign] foreign key ([IdDocumento]) references [DocumentosSolicitados] ([IdDocumentoSolicitado]);
        alter table [ProcesosDocumentos] add constraint [procesosdocumentos_idmotivo_foreign] foreign key ([IdMotivo]) references [MotivosRechazo] ([IdMotivo]);
        create unique index [procesosdocumentos_idproceso_iddocumento_unique] on [ProcesosDocumentos] ([IdProceso], [IdDocumento]);
    END

    EXEC(N'
        INSERT INTO ProcesosDocumentos (IdProceso, IdDocumento, Estado, Ruta, NombreArchivo, IdUsuario, Fecha, updated_at)
        SELECT x.IdProceso, x.IdDocumento, x.Estado, x.Ruta, RIGHT(x.Ruta, CHARINDEX(''/'', REVERSE(x.Ruta)) - 1), x.IdUsuario, x.Fecha, x.Fecha
        FROM (
            SELECT p.IdProceso, ds.IdDocumentoSolicitado AS IdDocumento, p.IdUsuario, ISNULL(p.updated_at, ISNULL(p.FechaCreacion, GETDATE())) AS Fecha,
                   CASE WHEN CHARINDEX('','' + CAST(ds.IdDocumentoSolicitado AS varchar(20)) + '','', '','' + ISNULL(p.DocumentosAprobados, '''')) > 0 THEN ''aprobado'' ELSE ''cargado'' END AS Estado,
                   CASE WHEN ds.IdDocumentoSolicitado = 2
                        THEN (SELECT TOP 1 N''app/public/tratamiento_datos/'' + RIGHT(td.RutaFormato, CHARINDEX(''/'', REVERSE(td.RutaFormato)) - 1)
                              FROM TratamientoDatos td WHERE td.IdProceso = p.IdProceso AND CHARINDEX(''/'', td.RutaFormato) > 0 ORDER BY td.IdTratamiento DESC)
                        ELSE N''app/public/documentos-soporte-'' + CAST(t.DocumentoTercero AS nvarchar(20)) + N''-'' + CAST(p.IdProceso AS nvarchar(20)) + N''/''
                             + CAST(t.DocumentoTercero AS nvarchar(20)) + N''-'' + CAST(p.IdProceso AS nvarchar(20)) + N''-'' + REPLACE(ds.NombreDocumento, '' '', '''') + N''.pdf''
                   END AS Ruta
            FROM Procesos p
            INNER JOIN Terceros t ON t.IdTercero = p.IdTercero
            INNER JOIN DocumentosSolicitados ds
                ON CHARINDEX('','' + CAST(ds.IdDocumentoSolicitado AS varchar(20)) + '','', '','' + ISNULL(p.DocumentosCargados, '''')) > 0
                OR CHARINDEX('','' + CAST(ds.IdDocumentoSolicitado AS varchar(20)) + '','', '','' + ISNULL(p.DocumentosAprobados, '''')) > 0
            UNION ALL
            SELECT p.IdProceso, 2, p.IdUsuario, ISNULL(p.updated_at, ISNULL(p.FechaCreacion, GETDATE())), ''cargado'',
                   (SELECT TOP 1 N''app/public/tratamiento_datos/'' + RIGHT(td.RutaFormato, CHARINDEX(''/'', REVERSE(td.RutaFormato)) - 1)
                    FROM TratamientoDatos td WHERE td.IdProceso = p.IdProceso AND CHARINDEX(''/'', td.RutaFormato) > 0 ORDER BY td.IdTratamiento DESC)
            FROM Procesos p
            WHERE CHARINDEX('',2,'', '','' + ISNULL(p.DocumentosCargados, '''')) = 0 AND CHARINDEX('',2,'', '','' + ISNULL(p.DocumentosAprobados, '''')) = 0
              AND EXISTS (SELECT 1 FROM TratamientoDatos td WHERE td.IdProceso = p.IdProceso AND CHARINDEX(''/'', td.RutaFormato) > 0)
        ) x
        WHERE NOT EXISTS (SELECT 1 FROM ProcesosDocumentos d WHERE d.IdProceso = x.IdProceso AND d.IdDocumento = x.IdDocumento)');

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

    DROP TABLE ProcesosDocumentos;
    DROP TABLE ProcesosHistorial;
    DROP TABLE MotivosRechazo;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0
        ROLLBACK TRANSACTION;
    THROW;
END CATCH

 */
