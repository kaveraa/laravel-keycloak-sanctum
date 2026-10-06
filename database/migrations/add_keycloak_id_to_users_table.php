<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Column that links a user to the Keycloak account (claim "sub").
 * Not needed if you identify users by e-mail or by username:
 * in that case, delete this migration after publishing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'keycloak_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('keycloak_id')->nullable()->unique();
        });

        // The password is no longer required: users log in with Keycloak
        if (Schema::hasColumn('users', 'password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('password')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['keycloak_id']);
            $table->dropColumn('keycloak_id');
        });
    }
};
