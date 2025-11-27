<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bri_va_payment_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // RELASI KE bri_clients.client_id
            $table->string('client_id', 255)
                ->nullable()
                ->comment('BRI client_id reference from bri_clients');
                
            // * Multi-tenant
            $table->string('tenant_id', 100)->nullable()->comment('Identifier for tenant, used in multi-tenant architecture');
            $table->string('reff_id', 100)->nullable()->comment('Reference ID from related transaction, e.g. sales or order');


            $table->string('customer_no', 100)->nullable();
            $table->string('customer_name', 100)->nullable();
            $table->string('bri_va_number', 50)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->enum('status', ['PENDING', 'WAITING_PAYMENT', 'PAID', 'EXPIRED', 'FAILED', 'CANCELED'])->default('PENDING');

            $table->string('external_id', 100)->nullable()->comment('Unique internal request ID');

            $table->dateTime('expired_at')->nullable();
            $table->json('request_payload')->nullable()->comment('Payload sent to BRI API when creating VA');
            $table->json('response_payload')->nullable()->comment('Response from BRI API when creating VA');
            $table->json('callback_payload')->nullable()->comment('Payload received from BRI callback');
            $table->dateTime('paid_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id']);
            $table->index(['reff_id']);
            $table->index(['bri_va_number']);
            $table->index(['client_id']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bri_va_payment_logs');
    }
};
