<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programas académicos de la FET (Tabla 1).
     */
    public function up(): void
    {
        Schema::create('programa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 120)->unique();
            $table->smallInteger('total_semestres');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa');
    }
};
