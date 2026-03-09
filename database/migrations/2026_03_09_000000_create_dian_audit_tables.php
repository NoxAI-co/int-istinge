<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDianAuditTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dian_audit_sessions', function (Blueprint $col) {
            $col->id();
            $col->string('filename');
            $col->string('original_filename');
            $col->integer('uploaded_by')->unsigned();
            $col->string('periodo');
            $col->integer('total_records')->default(0);
            $col->integer('matched')->default(0);
            $col->integer('discrepancies')->default(0);
            $col->integer('not_found')->default(0);
            $col->integer('corrected')->default(0);
            $col->decimal('monto_total_discrepancia', 18, 2)->default(0);
            $col->enum('status', ['processing', 'completed', 'error'])->default('processing');
            $col->text('error_message')->nullable();
            $col->timestamps();

            $col->foreign('uploaded_by')->references('id')->on('users');
        });

        Schema::create('dian_audit_records', function (Blueprint $col) {
            $col->id();
            $col->unsignedBigInteger('session_id');
            $col->string('tipo_documento')->nullable();
            $col->string('cufe')->index();
            $col->string('folio')->nullable();
            $col->string('prefijo')->nullable();
            $col->string('nit_receptor_dian');
            $col->string('nombre_receptor_dian');
            $col->string('nit_emisor')->nullable();
            $col->string('nombre_emisor')->nullable();
            $col->date('fecha_emision');
            $col->decimal('total', 18, 2);
            $col->enum('status', ['matched', 'discrepancy', 'not_found', 'corrected'])->default('not_found');
            
            // Sistema fields
            $col->integer('factura_id')->unsigned()->nullable();
            $col->string('nit_receptor_sistema')->nullable();
            $col->string('nombre_receptor_sistema')->nullable();
            $col->integer('cliente_id_sistema')->unsigned()->nullable();
            $col->timestamps();

            $col->foreign('session_id')->references('id')->on('dian_audit_sessions')->onDelete('cascade');
            $col->foreign('factura_id')->references('id')->on('factura');
            $col->foreign('cliente_id_sistema')->references('id')->on('contactos');
        });

        Schema::create('dian_audit_logs', function (Blueprint $col) {
            $col->id();
            $col->unsignedBigInteger('audit_record_id');
            $col->unsignedBigInteger('session_id');
            $col->integer('factura_id')->unsigned();
            $col->string('folio')->nullable();
            $col->string('cufe')->nullable();
            
            $col->string('nit_anterior')->nullable();
            $col->string('nombre_anterior')->nullable();
            $col->integer('cliente_id_anterior')->unsigned()->nullable();
            
            $col->string('nit_nuevo');
            $col->string('nombre_nuevo');
            $col->integer('cliente_id_nuevo')->unsigned();
            
            $col->text('motivo');
            $col->integer('usuario_id')->unsigned();
            $col->string('ip', 45);
            $col->text('user_agent');
            $col->timestamps();

            $col->foreign('audit_record_id')->references('id')->on('dian_audit_records');
            $col->foreign('session_id')->references('id')->on('dian_audit_sessions');
            $col->foreign('factura_id')->references('id')->on('factura');
            $col->foreign('usuario_id')->references('id')->on('users');
            $col->foreign('cliente_id_anterior')->references('id')->on('contactos');
            $col->foreign('cliente_id_nuevo')->references('id')->on('contactos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dian_audit_logs');
        Schema::dropIfExists('dian_audit_records');
        Schema::dropIfExists('dian_audit_sessions');
    }
}
