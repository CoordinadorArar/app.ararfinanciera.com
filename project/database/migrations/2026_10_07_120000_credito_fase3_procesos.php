<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreditoFase3Procesos extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('MotivosRechazo')) {
            Schema::create('MotivosRechazo', function (Blueprint $table) {
                $table->integer('IdMotivo')->primary();
                $table->string('NombreMotivo', 150);
                $table->boolean('EstadoMotivo')->default(1);
            });
        }

        DB::statement(
            "INSERT INTO MotivosRechazo (IdMotivo, NombreMotivo, EstadoMotivo)
             SELECT v.IdMotivo, v.NombreMotivo, 1
             FROM (VALUES (1, N'No tiene cupo'), (2, N'Mal hábito de pago'), (3, N'Compra cartera en proceso'),
                          (4, N'No cumple política de antigüedad'), (5, N'Proceso jurídico en curso'), (6, N'No cumple política compra de cartera')) v (IdMotivo, NombreMotivo)
             WHERE NOT EXISTS (SELECT 1 FROM MotivosRechazo m WHERE m.IdMotivo = v.IdMotivo)"
        );

        if (!Schema::hasTable('ProcesosHistorial')) {
            Schema::create('ProcesosHistorial', function (Blueprint $table) {
                $table->bigIncrements('IdHistorial');
                $table->bigInteger('IdProceso');
                $table->integer('EstadoAnterior')->nullable();
                $table->integer('EstadoNuevo');
                $table->integer('IdMotivo')->nullable();
                $table->string('Observacion', 500)->nullable();
                $table->bigInteger('IdUsuario')->nullable();
                $table->dateTime('Fecha')->default(DB::raw('getdate()'));
                $table->foreign('IdProceso')->references('IdProceso')->on('Procesos');
                $table->foreign('IdMotivo')->references('IdMotivo')->on('MotivosRechazo');
                $table->index('IdProceso');
            });
        }

        DB::statement(
            "INSERT INTO ProcesosHistorial (IdProceso, EstadoAnterior, EstadoNuevo, IdMotivo, Observacion, IdUsuario, Fecha)
             SELECT p.IdProceso, NULL, p.EstadoProceso, NULL, N'Estado inicial (migración)', p.IdUsuario, ISNULL(p.FechaCreacion, GETDATE())
             FROM Procesos p
             WHERE p.EstadoProceso IS NOT NULL AND NOT EXISTS (SELECT 1 FROM ProcesosHistorial h WHERE h.IdProceso = p.IdProceso)"
        );

        if (!Schema::hasTable('ProcesosDocumentos')) {
            Schema::create('ProcesosDocumentos', function (Blueprint $table) {
                $table->bigIncrements('IdProcesoDocumento');
                $table->bigInteger('IdProceso');
                $table->bigInteger('IdDocumento');
                $table->string('Estado', 20);
                $table->string('Ruta', 255)->nullable();
                $table->string('NombreArchivo', 150)->nullable();
                $table->integer('IdMotivo')->nullable();
                $table->string('Observacion', 500)->nullable();
                $table->bigInteger('IdUsuario')->nullable();
                $table->dateTime('Fecha')->default(DB::raw('getdate()'));
                $table->dateTime('updated_at')->nullable();
                $table->foreign('IdProceso')->references('IdProceso')->on('Procesos');
                $table->foreign('IdDocumento')->references('IdDocumentoSolicitado')->on('DocumentosSolicitados');
                $table->foreign('IdMotivo')->references('IdMotivo')->on('MotivosRechazo');
                $table->unique(['IdProceso', 'IdDocumento']);
            });
        }

        DB::statement(
            "INSERT INTO ProcesosDocumentos (IdProceso, IdDocumento, Estado, Ruta, NombreArchivo, IdUsuario, Fecha, updated_at)
             SELECT x.IdProceso, x.IdDocumento, x.Estado, x.Ruta, RIGHT(x.Ruta, CHARINDEX('/', REVERSE(x.Ruta)) - 1), x.IdUsuario, x.Fecha, x.Fecha
             FROM (
                 SELECT p.IdProceso, ds.IdDocumentoSolicitado AS IdDocumento, p.IdUsuario, ISNULL(p.updated_at, ISNULL(p.FechaCreacion, GETDATE())) AS Fecha,
                        CASE WHEN CHARINDEX(',' + CAST(ds.IdDocumentoSolicitado AS varchar(20)) + ',', ',' + ISNULL(p.DocumentosAprobados, '')) > 0 THEN 'aprobado' ELSE 'cargado' END AS Estado,
                        CASE WHEN ds.IdDocumentoSolicitado = 2
                             THEN (SELECT TOP 1 N'app/public/tratamiento_datos/' + RIGHT(td.RutaFormato, CHARINDEX('/', REVERSE(td.RutaFormato)) - 1)
                                   FROM TratamientoDatos td WHERE td.IdProceso = p.IdProceso AND CHARINDEX('/', td.RutaFormato) > 0 ORDER BY td.IdTratamiento DESC)
                             ELSE N'app/public/documentos-soporte-' + CAST(t.DocumentoTercero AS nvarchar(20)) + N'-' + CAST(p.IdProceso AS nvarchar(20)) + N'/'
                                  + CAST(t.DocumentoTercero AS nvarchar(20)) + N'-' + CAST(p.IdProceso AS nvarchar(20)) + N'-' + REPLACE(ds.NombreDocumento, ' ', '') + N'.pdf'
                        END AS Ruta
                 FROM Procesos p
                 INNER JOIN Terceros t ON t.IdTercero = p.IdTercero
                 INNER JOIN DocumentosSolicitados ds
                     ON CHARINDEX(',' + CAST(ds.IdDocumentoSolicitado AS varchar(20)) + ',', ',' + ISNULL(p.DocumentosCargados, '')) > 0
                     OR CHARINDEX(',' + CAST(ds.IdDocumentoSolicitado AS varchar(20)) + ',', ',' + ISNULL(p.DocumentosAprobados, '')) > 0
                 UNION ALL
                 SELECT p.IdProceso, 2, p.IdUsuario, ISNULL(p.updated_at, ISNULL(p.FechaCreacion, GETDATE())), 'cargado',
                        (SELECT TOP 1 N'app/public/tratamiento_datos/' + RIGHT(td.RutaFormato, CHARINDEX('/', REVERSE(td.RutaFormato)) - 1)
                         FROM TratamientoDatos td WHERE td.IdProceso = p.IdProceso AND CHARINDEX('/', td.RutaFormato) > 0 ORDER BY td.IdTratamiento DESC)
                 FROM Procesos p
                 WHERE CHARINDEX(',2,', ',' + ISNULL(p.DocumentosCargados, '')) = 0 AND CHARINDEX(',2,', ',' + ISNULL(p.DocumentosAprobados, '')) = 0
                   AND EXISTS (SELECT 1 FROM TratamientoDatos td WHERE td.IdProceso = p.IdProceso AND CHARINDEX('/', td.RutaFormato) > 0)
             ) x
             WHERE NOT EXISTS (SELECT 1 FROM ProcesosDocumentos d WHERE d.IdProceso = x.IdProceso AND d.IdDocumento = x.IdDocumento)"
        );
    }

    public function down()
    {
        Schema::dropIfExists('ProcesosDocumentos');
        Schema::dropIfExists('ProcesosHistorial');
        Schema::dropIfExists('MotivosRechazo');
    }
}
