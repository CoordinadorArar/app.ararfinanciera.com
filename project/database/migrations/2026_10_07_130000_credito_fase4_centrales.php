<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreditoFase4Centrales extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('ConsultasCentrales')) {
            Schema::create('ConsultasCentrales', function (Blueprint $table) {
                $table->bigIncrements('Id');
                $table->bigInteger('IdProceso')->nullable();
                $table->bigInteger('IdTercero');
                $table->string('Proveedor', 30);
                $table->string('Ambiente', 20)->nullable();
                $table->boolean('Simulado')->default(0);
                $table->dateTime('FechaConsulta')->default(DB::raw('getdate()'));
                $table->dateTime('VigenteHasta')->nullable();
                $table->bigInteger('IdUsuario')->nullable();
                $table->boolean('Exitosa')->default(0);
                $table->string('MensajeError', 500)->nullable();
                $table->longText('ResumenJson')->nullable();
                $table->longText('RespuestaCruda')->nullable();
                $table->foreign('IdProceso')->references('IdProceso')->on('Procesos');
                $table->foreign('IdTercero')->references('IdTercero')->on('Terceros');
                $table->index(['IdTercero', 'Proveedor', 'FechaConsulta']);
                $table->index('IdProceso');
            });
        }

        if (!Schema::hasTable('ConfiguracionCentrales')) {
            Schema::create('ConfiguracionCentrales', function (Blueprint $table) {
                $table->increments('IdConfiguracion');
                $table->string('Clave', 50)->unique();
                $table->string('Valor', 100);
                $table->bigInteger('IdUsuario')->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        DB::statement(
            "INSERT INTO ConfiguracionCentrales (Clave, Valor)
             SELECT v.Clave, v.Valor
             FROM (VALUES (N'predeterminado', N'transunion'), (N'vigenciaDias', N'30'), (N'habilitado.transunion', N'1'), (N'habilitado.datacredito', N'1')) v (Clave, Valor)
             WHERE NOT EXISTS (SELECT 1 FROM ConfiguracionCentrales c WHERE c.Clave = v.Clave)"
        );
    }

    public function down()
    {
        Schema::dropIfExists('ConfiguracionCentrales');
        Schema::dropIfExists('ConsultasCentrales');
    }
}
