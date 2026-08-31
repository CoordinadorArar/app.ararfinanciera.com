<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubmenusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('submenus', function (Blueprint $table) {
            $table->bigIncrements('idSubmenu');
            $table->bigInteger('idMenu')->unsigned();
            $table->foreign('idMenu')->references('idMenu')->on('menus');
            $table->string('nombreSubmenu',30);
            $table->string('rutaSubmenu',30);
            $table->boolean('estadoSubmenu');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('submenus');
    }
}
