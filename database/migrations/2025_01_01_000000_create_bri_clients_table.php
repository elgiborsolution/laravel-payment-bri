<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bri_clients');

        Schema::create('bri_clients', function (Blueprint $table) {
            $table->id();

            // Nama perusahaan / tenant
            $table->string('name', 255)->nullable();

            // Multitenant setup
            $table->string('tenant_id', 255)->nullable()->index()->comment('For multi-tenant case');

            // COMMON (SNAP)
            $table->string('base_url', 255)
                ->nullable()
                ->comment('Override base URL. Default https://sandbox.partner.api.bri.co.id');

            $table->string('client_id', 255)->unique();
            $table->string('client_secret', 255)->nullable()->unique();
            $table->text('private_key')->nullable();

            // QRIS CONFIG
            $table->string('qris_partner_id', 255)->nullable();
            $table->string('qris_channel_id')->nullable();
            $table->string('qris_merchant_id', 255)->nullable();
            $table->string('qris_terminal_id', 255)->nullable();
            $table->text('qris_public_key')->nullable();

            // BRIVA CONFIG
            $table->string('briva_partner_service_id', 255)->nullable();
            $table->string('briva_partner_id', 255)->nullable();
            $table->string('briva_channel_id', 255)->nullable();
            $table->text('briva_public_key')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bri_clients');
    }
};
