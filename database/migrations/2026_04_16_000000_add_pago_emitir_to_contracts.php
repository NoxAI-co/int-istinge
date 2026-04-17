<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPagoEmitirToContracts extends Migration
{
    public function up()
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->tinyInteger('pago_emitir')->default(0)->after('pago_siigo_contrato');
        });
    }

    public function down()
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('pago_emitir');
        });
    }
}
