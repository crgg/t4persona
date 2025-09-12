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
        DB::statement("CREATE INDEX sessions_assistant_id_idx ON sessions (assistant_id)");
        DB::statement("CREATE INDEX sessions_user_id_idx ON sessions (user_id)");
        DB::statement("CREATE INDEX sessions_date_start_idx ON sessions (date_start)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sessions_one_open_per_assistant');
        DB::statement('DROP INDEX IF EXISTS sessions_assistant_id_idx');
        DB::statement('DROP INDEX IF EXISTS sessions_user_id_idx');
        DB::statement('DROP INDEX IF EXISTS sessions_date_start_idx');
    }
};
