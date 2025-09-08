<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('assistant_id');
            $table->foreign('assistant_id')->references('id')->on('assistants')->cascadeOnDelete();

            $table->string('type', 20);          // audio|video|image|text (doc)
            $table->text('storage_url');
            $table->text('transcription')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('date_upload')->useCurrent();
        });

        DB::statement('ALTER TABLE media ALTER COLUMN metadata TYPE jsonb USING metadata::jsonb');
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
