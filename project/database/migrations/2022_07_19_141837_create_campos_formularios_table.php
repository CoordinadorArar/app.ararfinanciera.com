<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCamposFormulariosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('campos_formularios', function (Blueprint $table) {
            $table->bigIncrements('idCampoFormulario');
            $table->string('nombreCampo');
            $table->string('codigoCampo');
            $table->bigInteger('tipoCampo')->unsigned();
            $table->foreign('tipoCampo')->references('idTipoCampo')->on('tipos_campos');
            $table->boolean('estadoCampo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('campos_formularios');
    }
}
