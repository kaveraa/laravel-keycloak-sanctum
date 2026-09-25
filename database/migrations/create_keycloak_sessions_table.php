<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keycloak_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_access_token_id')
                ->unique()
                ->constrained('personal_access_tokens')
                ->cascadeOnDelete();
            $table->string('sid')->nullable()->index();
            $table->string('sub')->index();
            $table->json('roles');
            $table->text('id_token')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keycloak_sessions');
    }
};
