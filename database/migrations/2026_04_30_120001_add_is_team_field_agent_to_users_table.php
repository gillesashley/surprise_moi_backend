<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CREATE INDEX CONCURRENTLY cannot run inside a transaction on Postgres.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_team_field_agent')->default(false);
        });

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS users_is_team_field_agent_index ON users (is_team_field_agent)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS users_is_team_field_agent_index');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_team_field_agent');
        });
    }
};
