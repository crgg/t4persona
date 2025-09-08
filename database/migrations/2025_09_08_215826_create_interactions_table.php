<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('interactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('generated_session_id');
            $table->foreign('generated_session_id')->references('id')->on('generated_sessions')->cascadeOnDelete();

            $table->text('text_from_user')->nullable();
            $table->text('assistant_text_response')->nullable();
            $table->text('assistant_audio_response')->nullable();
            $table->string('emotion_deteted', 100)->nullable();  // <- escritura EXACTA del documento
            $table->timestampTz('timestamp')->useCurrent();      // <- nombre tal cual documento
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
