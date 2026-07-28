<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de pruebas de conexión a MikroTik. Cada vez que se sondea un router
 * (botón "Probar conexión" del módulo Mikrotik, o un chequeo automático) se deja
 * un renglón con el resultado, la latencia y el motivo del fallo. Sirve para dar
 * SEGUIMIENTO: ver si una MikroTik viene fallando de forma intermitente y desde
 * cuándo, en vez de solo saber su estado actual.
 *
 * Para BDs `integra_*` legacy la tabla también se crea desde
 * fix-legacy-columns.sh (bloque 15).
 */
class CreateMikrotikConexionLogsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('mikrotik_conexion_logs')) {
            return;
        }

        Schema::create('mikrotik_conexion_logs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('mikrotik_id')->index();
            $t->integer('empresa')->nullable()->index();
            $t->boolean('ok')->default(0);
            $t->integer('latencia_ms')->default(0);
            $t->string('mensaje', 255)->nullable();   // motivo del fallo (o null si OK)
            $t->string('board', 100)->nullable();
            $t->string('version', 50)->nullable();
            $t->string('uptime', 50)->nullable();
            $t->string('cpu_load', 20)->nullable();
            $t->string('origen', 20)->default('manual'); // manual | cron
            $t->integer('created_by')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['mikrotik_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('mikrotik_conexion_logs');
    }
}
