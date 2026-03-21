<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWhatsappSyncColumnsToLogMetaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('log_meta', function (Blueprint $table) {
            // Identificador del registro remoto en whatsapp_messages
            $table->unsignedBigInteger('remote_id')->nullable()->after('id');

            // Identificador de mensaje de WhatsApp (wamid)
            $table->string('wamid', 500)->nullable()->after('remote_id');

            // Relaciones con documentos remotos
            $table->unsignedInteger('incoming_invoice_id')->nullable()->after('factura_id');
            $table->unsignedInteger('incoming_contract_id')->nullable()->after('incoming_invoice_id');
            $table->unsignedInteger('incoming_payment_id')->nullable()->after('incoming_contract_id');

            // NIT de la empresa en la API central
            $table->unsignedInteger('incoming_company_nit')->nullable()->after('empresa');

            // Dirección y estado del mensaje según META
            $table->enum('direction', ['inbound', 'outbound'])->nullable()->after('enviado_por');

            // Estado del mensaje en la API central (sobrescribe el uso anterior de status)
            // Valores esperados: sent, delivered, read, failed, pending, etc.
            $table->string('status', 50)->nullable()->change();

            // Información de error y metadata adicional
            $table->text('error_message')->nullable()->after('response');
            $table->longText('metadata')->nullable()->after('error_message');

            // Timestamps del mensaje remoto
            $table->timestamp('sent_at')->nullable()->after('metadata');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');

            // Índices útiles para consultas
            $table->index('remote_id');
            $table->index('wamid');
            $table->index('incoming_invoice_id');
            $table->index('incoming_payment_id');
            $table->index('incoming_contract_id');
            $table->index('incoming_company_nit');
            $table->index('status');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('log_meta', function (Blueprint $table) {
            $table->dropIndex(['remote_id']);
            $table->dropIndex(['wamid']);
            $table->dropIndex(['incoming_invoice_id']);
            $table->dropIndex(['incoming_payment_id']);
            $table->dropIndex(['incoming_contract_id']);
            $table->dropIndex(['incoming_company_nit']);
            $table->dropIndex(['status']);
            $table->dropIndex(['sent_at']);

            $table->dropColumn([
                'remote_id',
                'wamid',
                'incoming_invoice_id',
                'incoming_contract_id',
                'incoming_payment_id',
                'incoming_company_nit',
                'direction',
                'error_message',
                'metadata',
                'sent_at',
                'delivered_at',
                'read_at',
            ]);
        });
    }
}

