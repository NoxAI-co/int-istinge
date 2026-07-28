<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddContEnvioFallidoToFacturaAndIngresos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ingresos', function (Blueprint $table) {
            if (!Schema::hasColumn('ingresos', 'cont_envio_fallido')) {
                $table->integer('cont_envio_fallido')->default(0)->after('cont_message_undeliverable')->comment('reintentos por fallos atribuibles a la cuenta emisora (pago, token, rate-limit), no al destinatario');
            }
        });

        Schema::table('factura', function (Blueprint $table) {
            if (!Schema::hasColumn('factura', 'cont_envio_fallido')) {
                $table->integer('cont_envio_fallido')->default(0)->after('cont_message_undeliverable')->comment('reintentos por fallos atribuibles a la cuenta emisora (pago, token, rate-limit), no al destinatario');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ingresos', function (Blueprint $table) {
            if (Schema::hasColumn('ingresos', 'cont_envio_fallido')) {
                $table->dropColumn('cont_envio_fallido');
            }
        });

        Schema::table('factura', function (Blueprint $table) {
            if (Schema::hasColumn('factura', 'cont_envio_fallido')) {
                $table->dropColumn('cont_envio_fallido');
            }
        });
    }
}
