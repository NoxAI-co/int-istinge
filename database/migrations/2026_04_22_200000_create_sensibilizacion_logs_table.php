<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSensibilizacionLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sensibilizacion_logs', function (Blueprint $blueprint) {
            $blueprint->bigIncrements('id');
            $blueprint->string('celular', 20);
            $blueprint->unsignedBigInteger('contacto_id')->nullable();
            $blueprint->string('contacto_nombre')->nullable();
            $blueprint->string('template');
            $blueprint->string('batch_id');
            $blueprint->integer('batch_number');
            $blueprint->string('idempotency_key')->unique();
            $blueprint->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $blueprint->text('api_response')->nullable();
            $blueprint->text('error_message')->nullable();
            $blueprint->string('image_url')->nullable();
            $blueprint->string('optional_1')->nullable();
            $blueprint->dateTime('campaign_date');
            $blueprint->unsignedBigInteger('created_by')->nullable();
            $blueprint->timestamps();

            $blueprint->index('celular');
            $blueprint->index('batch_id');
            $blueprint->index('status');
            $blueprint->index('campaign_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sensibilizacion_logs');
    }
}
