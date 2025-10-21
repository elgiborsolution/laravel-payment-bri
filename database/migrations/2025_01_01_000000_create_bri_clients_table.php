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

            $table->string('name', 255)->nullable(); // Nama perusahaan / tenant
            // Jika multi tenant → bisa tambahkan tenant_id (nullable jika tidak digunakan)
            $table->string('tenant_id', 255)->nullable()->index();

            $table->string('client_id', 255)->unique();
            $table->string('client_secret', 255)->nullable()->unique();
            $table->text('public_key')->nullable();  // Public key for signature verification
            $table->text('private_key')->nullable();  // Public key for signature verification

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
