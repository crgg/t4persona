<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personalities', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('assistant_id');
            $table->foreign('assistant_id')->references('id')->on('assistants')->cascadeOnDelete();

            $table->string('model', 50);
            $table->json('result_json');
            $table->timestampTz('date_of_generation')->useCurrent();
        });

        DB::statement('ALTER TABLE personalities ALTER COLUMN result_json TYPE jsonb USING result_json::jsonb');
    }

    public function down(): void
    {
        Schema::dropIfExists('personalities');
    }
};
