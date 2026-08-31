<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermisosCamposTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('permisos_campos', function (Blueprint $table) {
            $table->bigIncrements('idPermisosCampos');
            $table->bigInteger('idPerfil')->unsigned();
            $table->foreign('idPerfil')->references('idPerfil')->on('perfiles');
            $table->bigInteger('idCampo')->unsigned();
            $table->foreign('idCampo')->references('idCampoFormulario')->on('campos_formularios');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('permisos_campos');
    }
}
