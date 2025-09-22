<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Http\Controllers\Questions\QuestionTools;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();


            $table->string('instrument', 20)->default('BFI-44');
            $table->unsignedSmallInteger('position')->nullable();


            $table->enum('dimension', [
                'openness',
                'conscientiousness',
                'extraversion',
                'agreeableness',
                'neuroticism',
            ])->index();
            $table->boolean('is_reverse')->default(false);


            $table->text('question');
            $table->boolean('is_active')->default(true);

            $table->timestamps();


            $table->index(['instrument', 'position']);
            $table->index(['dimension', 'is_active']);


            $table->unique(['instrument', 'position']);
        });

        QuestionTools::generate_questions();
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
