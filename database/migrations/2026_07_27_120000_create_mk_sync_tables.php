<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sincronización masiva a MikroTik por lotes. `mk_sync_lotes` = cabecera de la
 * orden (progreso, estado, lock); `mk_sync_items` = un renglón por contrato con
 * su resultado. El procesador avanza en tandas cortas y actualiza el progreso.
 *
 * Para BDs `integra_*` legacy estas tablas también se crean desde
 * fix-legacy-columns.sh (bloque 14).
 */
class CreateMkSyncTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('mk_sync_lotes')) {
            Schema::create('mk_sync_lotes', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->integer('empresa')->index();
                $t->integer('mikrotik_id');
                $t->string('estado', 20)->default('pendiente'); // pendiente|ejecutando|completado|cancelado
                $t->integer('total')->default(0);
                $t->integer('procesados')->default(0);
                $t->integer('correctos')->default(0);
                $t->integer('fallidos')->default(0);
                $t->text('filtros')->nullable();       // JSON del filtro usado
                $t->string('lock_token', 40)->nullable();
                $t->dateTime('lock_expires')->nullable();
                $t->integer('created_by')->nullable();
                $t->dateTime('inicio')->nullable();
                $t->dateTime('fin')->nullable();
                $t->timestamps();
                $t->index(['empresa', 'estado']);
            });
        }

        if (! Schema::hasTable('mk_sync_items')) {
            Schema::create('mk_sync_items', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('lote_id')->index();
                $t->integer('contrato_id');
                $t->integer('contrato_nro')->nullable();
                $t->string('ip', 64)->nullable();
                $t->string('estado', 12)->default('pendiente'); // pendiente|ok|error
                $t->string('mensaje', 255)->nullable();
                $t->integer('intentos')->default(0);
                $t->timestamps();
                $t->index(['lote_id', 'estado']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('mk_sync_items');
        Schema::dropIfExists('mk_sync_lotes');
    }
}
