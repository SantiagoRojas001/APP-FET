<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tokens de las invitaciones para activar una cuenta creada por la
     * administración. Van aparte de los de recuperación porque duran más.
     */
    public function up(): void
    {
        Schema::create('invitacion_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitacion_tokens');
    }
};
