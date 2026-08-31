<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePagaduriaTercerosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pagaduria_terceros', function (Blueprint $table) {
            $table->bigIncrements('idPagaduriaTercero');
            $table->bigInteger('idTercero')->unsigned();
            $table->foreign('idTercero')->references('idTercero')->on('terceros');
            $table->bigInteger('idPagaduria')->unsigned();
            $table->foreign('idPagaduria')->references('idPagaduria')->on('pagadurias');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pagaduria_terceros');
    }
}
