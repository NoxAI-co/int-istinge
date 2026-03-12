<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCronDianTables extends Migration
{
    public function up()
    {
        // 1. Log de cada ejecución del cronjob
        Schema::create('cron_dian_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('empresa_id');
            $table->dateTime('inicio_ejecucion');
            $table->dateTime('fin_ejecucion')->nullable();
            $table->enum('estado', ['ejecutando', 'completado', 'error', 'parcial'])->default('ejecutando');
            $table->integer('total_a_emitir')->default(0);
            $table->integer('total_emitidas')->default(0);
            $table->integer('total_fallidas')->default(0);
            $table->integer('total_alertas_numeracion')->default(0);
            $table->string('lock_token', 100)->nullable();
            $table->enum('creado_por', ['automatico', 'manual'])->default('automatico');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('empresa_id');
            $table->index('estado');
            $table->index('inicio_ejecucion');
        });

        // 2. Detalle por cada factura procesada en un batch
        Schema::create('cron_dian_detalle', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('log_id');
            $table->unsignedInteger('factura_id');
            $table->string('factura_codigo', 10);
            $table->unsignedInteger('numeracion_id')->nullable();
            $table->enum('estado', ['pendiente', 'emitida', 'fallida', 'omitida_numeracion', 'duplicado_detectado'])->default('pendiente');
            $table->string('cufe', 150)->nullable();
            $table->integer('intento')->default(1);
            $table->text('mensaje')->nullable();
            $table->integer('tiempo_respuesta_ms')->nullable();
            $table->dateTime('procesado_en')->nullable();
            $table->timestamps();

            $table->index('log_id');
            $table->index('factura_id');
            $table->index('estado');
        });

        // 3. Alertas de numeraciones agotadas o vencidas
        Schema::create('cron_dian_alertas_numeracion', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('numeracion_id');
            $table->enum('tipo_alerta', ['rango_superado', 'fecha_vencida', 'sin_numeracion']);
            $table->integer('nro_ultimo_usado')->nullable();
            $table->integer('nro_limite')->nullable();
            $table->integer('cantidad_facturas_afectadas')->default(0);
            $table->tinyInteger('resuelta')->default(0);
            $table->timestamps();

            $table->index('empresa_id');
            $table->index('numeracion_id');
            $table->index('resuelta');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cron_dian_alertas_numeracion');
        Schema::dropIfExists('cron_dian_detalle');
        Schema::dropIfExists('cron_dian_logs');
    }
}
