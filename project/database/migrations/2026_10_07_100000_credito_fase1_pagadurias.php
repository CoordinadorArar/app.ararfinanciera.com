<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreditoFase1Pagadurias extends Migration
{
    public function up()
    {
        $reglaCreada = !Schema::hasColumn('Pagadurias', 'UsaReglaSMMLV');

        Schema::table('Pagadurias', function (Blueprint $table) use ($reglaCreada) {
            if (!Schema::hasColumn('Pagadurias', 'EstadoPagaduria')) {
                $table->boolean('EstadoPagaduria')->default(1);
            }
            if ($reglaCreada) {
                $table->boolean('UsaReglaSMMLV')->default(0);
            }
            if (!Schema::hasColumn('Pagadurias', 'UmbralSMMLV')) {
                $table->decimal('UmbralSMMLV', 5, 2)->default(2);
            }
        });

        if ($reglaCreada) {
            DB::table('Pagadurias')->whereIn('IdPagaduria', [6, 8])->update(['UsaReglaSMMLV' => 1]);
        }

        if (!Schema::hasTable('PagaduriasReglasEdad')) {
            Schema::create('PagaduriasReglasEdad', function (Blueprint $table) {
                $table->bigIncrements('IdReglaEdad');
                $table->bigInteger('IdPagaduria');
                $table->integer('EdadMin');
                $table->integer('EdadMax');
                $table->integer('PlazoMaximo');
                $table->decimal('PorcentajeSeguro', 9, 6);
                $table->timestamps();
                $table->foreign('IdPagaduria')->references('IdPagaduria')->on('Pagadurias');
            });
        }

        DB::statement(
            'INSERT INTO PagaduriasReglasEdad (IdPagaduria, EdadMin, EdadMax, PlazoMaximo, PorcentajeSeguro, created_at, updated_at)
             SELECT p.IdPagaduria, v.EdadMin, v.EdadMax, v.PlazoMaximo, v.PorcentajeSeguro, GETDATE(), GETDATE()
             FROM Pagadurias p
             CROSS JOIN (VALUES (18, 70, 120, 0.003000), (71, 74, 48, 0.003000), (75, 99, 48, 0.005625)) v (EdadMin, EdadMax, PlazoMaximo, PorcentajeSeguro)
             WHERE NOT EXISTS (SELECT 1 FROM PagaduriasReglasEdad r WHERE r.IdPagaduria = p.IdPagaduria)'
        );

        if (!Schema::hasTable('ConfiguracionAuditoria')) {
            Schema::create('ConfiguracionAuditoria', function (Blueprint $table) {
                $table->bigIncrements('Id');
                $table->string('Tabla', 100);
                $table->bigInteger('IdRegistro');
                $table->string('Campo', 100);
                $table->text('ValorAnterior')->nullable();
                $table->text('ValorNuevo')->nullable();
                $table->bigInteger('IdUsuario')->nullable();
                $table->dateTime('Fecha')->default(DB::raw('getdate()'));
                $table->index(['Tabla', 'IdRegistro']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('ConfiguracionAuditoria');
        Schema::dropIfExists('PagaduriasReglasEdad');
        Schema::table('Pagadurias', function (Blueprint $table) {
            $table->dropColumn(['UsaReglaSMMLV', 'UmbralSMMLV']);
        });
    }
}
