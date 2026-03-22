<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddContMessageUndeliverableToFacturaAndIngresos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ingresos', function (Blueprint $table) {
            if (!Schema::hasColumn('ingresos', 'cont_message_undeliverable')) {
                $table->integer('cont_message_undeliverable')->default(0)->after('whatsapp')->comment('indica cuantas veces se ha intentato mandar un mensaje con este error (probablemente el cliente no tiene wpp)');
            }
        });

        Schema::table('factura', function (Blueprint $table) {
            if (!Schema::hasColumn('factura', 'cont_message_undeliverable')) {
                $table->integer('cont_message_undeliverable')->default(0)->after('whatsapp')->comment('indica cuantas veces se ha intentato mandar un mensaje con este error (probablemente el cliente no tiene wpp)');
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
            if (Schema::hasColumn('ingresos', 'cont_message_undeliverable')) {
                $table->dropColumn('cont_message_undeliverable');
            }
        });

        Schema::table('factura', function (Blueprint $table) {
            if (Schema::hasColumn('factura', 'cont_message_undeliverable')) {
                $table->dropColumn('cont_message_undeliverable');
            }
        });
    }
}
