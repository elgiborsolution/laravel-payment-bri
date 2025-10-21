<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bri_access_tokens');
        Schema::create('bri_access_tokens', function (Blueprint $table) {
            $table->id();

            // Hubungkan ke clients (jadi bisa multi-tenant otomatis)
            $table->unsignedBigInteger('client_id');
            $table->foreign('client_id')->references('id')->on('bri_clients')->onDelete('cascade');

            $table->string('token')->unique();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bri_access_tokens');
    }
};
