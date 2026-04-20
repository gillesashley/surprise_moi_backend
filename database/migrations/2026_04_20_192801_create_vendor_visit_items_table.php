<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_visit_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('vendor_visit_id');
            $table->string('item_key');
            $table->string('category');
            $table->string('criticality');
            $table->boolean('passed')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('vendor_visit_id')
                ->references('id')->on('vendor_visits')
                ->cascadeOnDelete();
            $table->unique(['vendor_visit_id', 'item_key']);
            $table->index('vendor_visit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_visit_items');
    }
};
