<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->text('user_audio_url')->nullable()->after('text_from_user');

            $table->index('session_id');   // interactions_session_id_index
            $table->index('timestamp');    // interactions_timestamp_index
        });
    }

    public function down(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropIndex('interactions_session_id_index');
            $table->dropIndex('interactions_timestamp_index');

            $table->dropColumn('user_audio_url');
        });
    }
};
