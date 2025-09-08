<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Renombrar password -> password_hash (como en el documento)
            if (Schema::hasColumn('users', 'password')) {
                $table->renameColumn('password', 'password_hash');
            }

            if (!Schema::hasColumn('users', 'rol')) {
                $table->string('rol', 20)->default('user');
            }
            if (!Schema::hasColumn('users', 'date_register')) {
                $table->timestampTz('date_register')->useCurrent();
            }
            if (!Schema::hasColumn('users', 'last_login')) {
                $table->timestampTz('last_login')->nullable();
            }

            //if (Schema::hasColumn('users', 'email_verified_at')) {
            //    $table->dropColumn('email_verified_at');
            //}
            if (Schema::hasColumn('users', 'remember_token')) {
                $table->dropColumn('remember_token');
            }
            if (Schema::hasColumn('users', 'created_at')) {
                $table->dropColumn(['created_at','updated_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'password_hash') && !Schema::hasColumn('users', 'password')) {
                $table->renameColumn('password_hash', 'password');
            }
            if (Schema::hasColumn('users', 'rol'))         $table->dropColumn('rol');
            if (Schema::hasColumn('users', 'date_register')) $table->dropColumn('date_register');
            if (Schema::hasColumn('users', 'last_login'))  $table->dropColumn('last_login');

            // Restaurar columnas comunes si las eliminaste
            //if (!Schema::hasColumn('users', 'email_verified_at')) {
            //    $table->timestampTz('email_verified_at')->nullable();
            //}
            if (!Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
            if (!Schema::hasColumn('users', 'created_at')) {
                $table->timestampsTz();
            }
        });
    }
};
