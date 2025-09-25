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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('age')->nullable(true)->default(21);
            $table->string('avatar_path')->nullable(true);
            $table->string('alias')->nullable(true);
            $table->string('country')->nullable(true);
            $table->string('language')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('age');
            $table->dropColumn('avatar_path');
            $table->dropColumn('alias');
            $table->dropColumn('country');
            $table->dropColumn('language');
        });
    }
};
