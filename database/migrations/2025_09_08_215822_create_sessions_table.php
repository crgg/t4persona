<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('generated_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('assistant_id');
            $table->foreign('assistant_id')->references('id')->on('assistants')->cascadeOnDelete();

            $table->timestampTz('date_start')->useCurrent();
            $table->timestampTz('date_end')->nullable();
            $table->string('canal', 20)->default('text'); // <- tal cual PDF
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_sessions');
    }
};
