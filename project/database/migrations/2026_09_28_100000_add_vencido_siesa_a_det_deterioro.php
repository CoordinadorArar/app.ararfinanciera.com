<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVencidoSiesaADetDeterioro extends Migration
{
    public function up()
    {
        Schema::table('det_corte_saldo_siesa', function (Blueprint $table) {
            $table->decimal('saldo_vencido', 19, 4)->nullable();
        });

        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->decimal('capital_vencido_siesa', 19, 4)->nullable();
            $table->decimal('interes_vencido_siesa', 19, 4)->nullable();
        });
    }

    public function down()
    {
        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->dropColumn(['capital_vencido_siesa', 'interes_vencido_siesa']);
        });

        Schema::table('det_corte_saldo_siesa', function (Blueprint $table) {
            $table->dropColumn('saldo_vencido');
        });
    }
}
