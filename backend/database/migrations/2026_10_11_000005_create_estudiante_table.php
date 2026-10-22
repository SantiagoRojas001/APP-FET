<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos personales y académicos del estudiante o egresado (Tabla 6).
     */
    public function up(): void
    {
        Schema::create('estudiante', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('usuario_id')->unique();
            $table->integer('programa_id');
            $table->string('codigo', 20)->unique();
            $table->string('documento', 20)->unique();
            $table->string('nombres', 80);
            $table->string('apellidos', 80);
            $table->string('telefono', 20)->nullable();
            $table->smallInteger('semestre_actual');
            $table->boolean('es_egresado')->default(false);
            $table->string('disponibilidad', 100)->nullable();

            $table->foreign('usuario_id')->references('id')->on('usuario')->cascadeOnDelete();
            $table->foreign('programa_id')->references('id')->on('programa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante');
    }
};
