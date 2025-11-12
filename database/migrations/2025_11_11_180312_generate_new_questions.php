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
        QuestionTools::generate_questions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
