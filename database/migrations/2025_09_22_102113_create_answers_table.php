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
        Schema::create('answers', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('assistant_id')
                  ->constrained('assistants')
                  ->cascadeOnDelete();

            // Soporta varios instrumentos (BFI-44, TIPI-10, etc.)
            $table->string('instrument', 20)->default('BFI-44');

            /*
             * JSON por ítem (clave = question_id):
             * {
             *   "1": {"position":1,"question":"Is talkative.","dimension":"extraversion","is_reverse":false,"raw":4,"scored":4,"answered_at":"2025-09-22T15:40:00Z"},
             *   "6": {...}
             * }
             */
            $table->json('answers');

            // Meta del formulario
            $table->unsignedSmallInteger('items_expected')->nullable(); // ej. 44
            $table->unsignedSmallInteger('items_answered')->default(0);
            $table->timestamp('completed_at')->nullable();

            // ===== Promedios por rasgo (1..5) con 3 decimales =====
            $table->decimal('openness_avg',          4, 3)->nullable();
            $table->decimal('conscientiousness_avg', 4, 3)->nullable();
            $table->decimal('extraversion_avg',      4, 3)->nullable();
            $table->decimal('agreeableness_avg',     4, 3)->nullable();
            $table->decimal('neuroticism_avg',       4, 3)->nullable();

            $table->timestamps();

            // Enforce 1 fila por assistant + instrument
            $table->unique(['assistant_id','instrument']);
            $table->index(['assistant_id','instrument']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
