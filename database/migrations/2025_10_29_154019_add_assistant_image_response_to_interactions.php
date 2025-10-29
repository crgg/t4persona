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
        Schema::table('interactions', function (Blueprint $table) {
            $table->text('assistant_image_response')->nullable()->after('assistant_audio_response');
            $table->string('file_respond', 100)->nullable()->after('file_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropColumn('assistant_image_response');
            $table->dropColumn('file_respond');
        });
    }
};
