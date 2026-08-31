<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReferenciasTercerosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referencias_terceros', function (Blueprint $table) {
            $table->bigIncrements('idReferencia');
            $table->bigInteger('idTercero')->unsigned();
            $table->foreign('idTercero')->references('idTercero')->on('terceros');
            $table->string('tipoReferencia');
            $table->string('nombreReferencia',80);
            $table->string('direccionReferencia',50);
            $table->integer('documentoReferencia');
            $table->string('telefonoReferencia',15);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('referencias_terceros');
    }
}
