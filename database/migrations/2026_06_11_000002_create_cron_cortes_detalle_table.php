<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCronCortesDetalleTable extends Migration
{
    public function up()
    {
        Schema::create('cron_cortes_detalle', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('log_id')->index();
            $table->unsignedBigInteger('contrato_id')->nullable();
            $table->unsignedBigInteger('factura_id')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->unsignedBigInteger('grupo_corte_id')->nullable();
            $table->string('tipo', 20)->nullable()->comment('internet | tv');
            $table->string('resultado', 30)->nullable()->comment('cortado | omitido | error');
            $table->string('metodo', 50)->nullable()->comment('mikrotik | olt | pppoe | db_only');
            $table->text('descripcion')->nullable();
            $table->string('ip', 50)->nullable();
            $table->string('serial_onu', 100)->nullable();
            $table->unsignedInteger('mikrotik_id')->nullable();
            $table->text('error_detalle')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('log_id')->references('id')->on('cron_cortes_logs')->onDelete('cascade');
            $table->index(['log_id', 'resultado']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cron_cortes_detalle');
    }
}
