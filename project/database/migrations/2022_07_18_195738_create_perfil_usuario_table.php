<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePerfilUsuarioTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('perfil_usuario', function (Blueprint $table) {
            $table->bigIncrements('idPerfilUsuario');
            $table->bigInteger('idPerfil')->unsigned();
            $table->foreign('idPerfil')->references('idPerfil')->on('perfiles');
            $table->bigInteger('idUsuario')->unsigned();
            $table->foreign('idUsuario')->references('idUsuario')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('perfil_usuario');
    }
}
