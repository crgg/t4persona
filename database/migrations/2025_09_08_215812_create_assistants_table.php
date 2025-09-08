<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assistants', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('state', 20)->default('neutral');
            $table->json('base_personality')->nullable();
            $table->timestampTz('date_creation')->useCurrent();
        });

        // JSONB (sin índices extra, para mantenerlo tal cual el doc)
        DB::statement('ALTER TABLE assistants ALTER COLUMN base_personality TYPE jsonb USING base_personality::jsonb');
    }

    public function down(): void
    {
        Schema::dropIfExists('assistants');
    }
};
