<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermisosPerfilTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('permisos_perfil', function (Blueprint $table) {
            $table->bigIncrements('idPermisosPerfil');
            $table->bigInteger('idPerfil')->unsigned();
            $table->foreign('idPerfil')->references('idPerfil')->on('perfiles');
            $table->bigInteger('idSubmenu')->unsigned();
            $table->foreign('idSubmenu')->references('idSubmenu')->on('submenus');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('permisos_perfil');
    }
}
