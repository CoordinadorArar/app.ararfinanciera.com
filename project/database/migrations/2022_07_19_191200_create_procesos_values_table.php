<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProcesosValuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('procesos_values', function (Blueprint $table) {
            $table->bigIncrements('idProcesoValue');
            $table->bigInteger('idProceso')->unsigned();
            $table->foreign('idProceso')->references('idProceso')->on('procesos');
            $table->bigInteger('idFormulario')->unsigned();
            $table->foreign('idFormulario')->references('idFormulario')->on('formularios');
            $table->bigInteger('idCampo')->unsigned();
            $table->foreign('idCampo')->references('idCampoFormulario')->on('campos_formularios');
            $table->string('valorIngresado');
            $table->string('origen');
            $table->bigInteger('usuarioCreacion')->unsigned();
            $table->foreign('usuarioCreacion')->references('idUsuario')->on('users');
            $table->dateTime('fechaCreacion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('procesos_values');
    }
}
