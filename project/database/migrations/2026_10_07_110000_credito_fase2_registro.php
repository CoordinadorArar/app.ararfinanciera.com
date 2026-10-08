<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreditoFase2Registro extends Migration
{
    public function up()
    {
        Schema::table('Procesos', function (Blueprint $table) {
            if (!Schema::hasColumn('Procesos', 'EmailTratamientoEnviadoA')) {
                $table->string('EmailTratamientoEnviadoA', 100)->nullable();
            }
            if (!Schema::hasColumn('Procesos', 'FechaEmailTratamiento')) {
                $table->dateTime('FechaEmailTratamiento')->nullable();
            }
        });

        if (!Schema::hasColumn('TratamientoDatos', 'IdProceso')) {
            Schema::table('TratamientoDatos', function (Blueprint $table) {
                $table->bigInteger('IdProceso')->nullable();
            });
        }

        DB::statement(
            'UPDATE td SET IdProceso = (SELECT TOP 1 p.IdProceso FROM Procesos p WHERE p.IdTercero = td.IdTercero ORDER BY p.FechaCreacion DESC, p.IdProceso DESC)
             FROM TratamientoDatos td
             WHERE td.IdProceso IS NULL AND EXISTS (SELECT 1 FROM Procesos p WHERE p.IdTercero = td.IdTercero)'
        );

        DB::statement(
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'terceros_documentotercero_unique' AND object_id = OBJECT_ID(N'dbo.Terceros'))
                AND NOT EXISTS (SELECT 1 FROM Terceros GROUP BY DocumentoTercero HAVING COUNT(*) > 1)
                create unique index [terceros_documentotercero_unique] on [Terceros] ([DocumentoTercero])"
        );
    }

    public function down()
    {
        DB::statement(
            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'terceros_documentotercero_unique' AND object_id = OBJECT_ID(N'dbo.Terceros'))
                drop index [terceros_documentotercero_unique] on [Terceros]"
        );
        Schema::table('Procesos', function (Blueprint $table) {
            $table->dropColumn(['EmailTratamientoEnviadoA', 'FechaEmailTratamiento']);
        });
        Schema::table('TratamientoDatos', function (Blueprint $table) {
            $table->dropColumn('IdProceso');
        });
    }
}
