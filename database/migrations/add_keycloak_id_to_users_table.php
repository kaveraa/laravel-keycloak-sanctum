<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonne qui relie un utilisateur à son compte Keycloak (claim "sub").
 * Inutile si vous identifiez les utilisateurs par e-mail ou par nom d'utilisateur :
 * supprimez alors cette migration après la publication.
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

        // Le mot de passe n'est plus obligatoire : les utilisateurs se connectent avec Keycloak
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
