<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendor_application_services', function (Blueprint $table) {
            $table->foreignId('vendor_application_id')->constrained()->onDelete('cascade');
            $table->foreignId('bespoke_service_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->primary(['vendor_application_id', 'bespoke_service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_application_services');
    }
};
