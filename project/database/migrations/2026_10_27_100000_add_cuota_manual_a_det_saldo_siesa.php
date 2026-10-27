<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCuotaManualADetSaldoSiesa extends Migration
{
    public function up()
    {
        Schema::table('det_corte_saldo_siesa', function (Blueprint $table) {
            $table->integer('cuota_manual')->nullable();
            $table->boolean('vencimiento_factoring')->nullable();
            $table->decimal('saldo_vencido_siesa', 19, 4)->nullable();
        });
    }

    public function down()
    {
        Schema::table('det_corte_saldo_siesa', function (Blueprint $table) {
            $table->dropColumn(['cuota_manual', 'vencimiento_factoring', 'saldo_vencido_siesa']);
        });
    }
}
