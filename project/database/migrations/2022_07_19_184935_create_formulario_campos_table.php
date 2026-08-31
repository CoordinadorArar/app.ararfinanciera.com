<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormularioCamposTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('formulario_campos', function (Blueprint $table) {
            $table->bigIncrements('idFormularioCampo');
            $table->bigInteger('idFormulario')->unsigned();
            $table->foreign('idFormulario')->references('idFormulario')->on('formularios');
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
        Schema::dropIfExists('formulario_campos');
    }
}
