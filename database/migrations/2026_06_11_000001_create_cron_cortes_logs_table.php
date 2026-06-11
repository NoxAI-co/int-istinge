<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCronCortesLogsTable extends Migration
{
    public function up()
    {
        Schema::create('cron_cortes_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tipo', 20)->comment('internet | tv');
            $table->unsignedInteger('empresa')->nullable();
            $table->unsignedBigInteger('grupo_corte_id')->nullable()->index();
            $table->unsignedInteger('total_procesados')->default(0);
            $table->unsignedInteger('total_cortados')->default(0);
            $table->unsignedInteger('total_omitidos')->default(0);
            $table->unsignedInteger('total_errores')->default(0);
            $table->unsignedInteger('duracion_ms')->default(0);
            $table->unsignedInteger('ejecutado_por')->nullable()->comment('NULL = CRON automático');
            $table->json('contexto')->nullable();
            $table->timestamps();

            $table->index(['grupo_corte_id', 'tipo']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cron_cortes_logs');
    }
}
