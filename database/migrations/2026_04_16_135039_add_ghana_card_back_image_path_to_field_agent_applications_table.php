<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_agent_applications', function (Blueprint $table) {
            $table->string('ghana_card_back_image_path')->nullable()->after('ghana_card_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('field_agent_applications', function (Blueprint $table) {
            $table->dropColumn('ghana_card_back_image_path');
        });
    }
};
