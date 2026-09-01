<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos que exige el Contrato Único Convergente (Resolución CRC 7811 de 2025).
 *
 * El contrato digital pasa al modelo obligatorio de la CRC y el formato pide
 * datos que el sistema no guardaba: hasta ahora salían como línea en blanco
 * para diligenciar a mano.
 *
 * De la EMPRESA (se configuran una sola vez):
 *   · registro_tic          número de registro TIC del operador, va en la banda
 *                           de identificación de la primera página.
 *   · incremento_tarifario  tope anual de subida de tarifa, en %. La CRC obliga
 *                           a declararlo en el contrato.
 *
 * Del CONTRATO (varían por cliente):
 *   · cargo_conexion        valor total del cargo por conexión.
 *   · valor_diferido        cuánto de ese cargo se descontó o difirió; es la
 *                           base de la tabla «valor a pagar si termina el
 *                           contrato anticipadamente», que se CALCULA mes a mes
 *                           y por eso no se guarda.
 *   · renovacion_automatica y acepta_permanencia: las dos casillas que en el
 *                           modelo marca el usuario. Nacen en 0 = sin marcar,
 *                           que es lo prudente: dar por aceptada una permanencia
 *                           que el cliente no firmó sería peor que dejar la
 *                           casilla vacía.
 *   · beneficios_paquete    texto libre de «Beneficios del paquete».
 *   · servicios_adicionales texto libre de «Productos o servicios adicionales».
 *
 * Todas nulas o en 0: mientras nadie las llene, el contrato sale con la línea
 * en blanco, igual que el modelo de la CRC.
 *
 * Cada columna se comprueba antes de crearla porque las BDs de clientes no
 * corren `migrate` y algunas pueden traerlas ya aplicadas a mano.
 */
class AddCrc7811FieldsToContractsAndEmpresas extends Migration
{
    public function up()
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'registro_tic')) {
                $table->string('registro_tic', 60)->nullable();
            }
            if (! Schema::hasColumn('empresas', 'incremento_tarifario')) {
                $table->decimal('incremento_tarifario', 5, 2)->nullable();
            }
        });

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'cargo_conexion')) {
                $table->decimal('cargo_conexion', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('contracts', 'valor_diferido')) {
                $table->decimal('valor_diferido', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('contracts', 'renovacion_automatica')) {
                $table->tinyInteger('renovacion_automatica')->default(0);
            }
            if (! Schema::hasColumn('contracts', 'acepta_permanencia')) {
                $table->tinyInteger('acepta_permanencia')->default(0);
            }
            if (! Schema::hasColumn('contracts', 'beneficios_paquete')) {
                $table->text('beneficios_paquete')->nullable();
            }
            if (! Schema::hasColumn('contracts', 'servicios_adicionales')) {
                $table->string('servicios_adicionales', 255)->nullable();
            }
        });

        // Equipos entregados con el plan: es una tabla y no columnas en
        // `contracts` porque el modelo imprime una FILA por equipo (equipo ·
        // condición de entrega · precio), y un router, un decodificador y una
        // ONU son tres renglones.
        if (! Schema::hasTable('contratos_equipos')) {
            Schema::create('contratos_equipos', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('contrato_id');
                $table->string('equipo', 150);
                $table->string('condicion', 150)->nullable();
                $table->decimal('precio', 15, 2)->nullable();
                $table->timestamps();
                $table->index('contrato_id', 'idx_ce_contrato');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('contratos_equipos');

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'cargo_conexion', 'valor_diferido', 'renovacion_automatica',
                'acepta_permanencia', 'beneficios_paquete', 'servicios_adicionales',
            ]);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['registro_tic', 'incremento_tarifario']);
        });
    }
}
