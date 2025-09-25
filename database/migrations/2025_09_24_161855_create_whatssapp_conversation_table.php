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
        Schema::create('whatsapp_conversation', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('assistant_id')
                  ->constrained('assistants')
                  ->cascadeOnDelete();
            $table->string('zip_aws_path')->nullable(true);
            $table->json('conversation');
            $table->json('metadata');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation');
    }
};
