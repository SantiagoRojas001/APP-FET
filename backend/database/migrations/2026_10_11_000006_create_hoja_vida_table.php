<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hoja de vida en formato institucional (Tabla 7) y sus experiencias laborales (Tabla 8).
     */
    public function up(): void
    {
        Schema::create('hoja_vida', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('estudiante_id')->unique();
            $table->text('perfil_profesional')->nullable();
            $table->text('formacion')->nullable();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('estudiante_id')->references('id')->on('estudiante')->cascadeOnDelete();
        });

        Schema::create('experiencia', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('hoja_vida_id');
            $table->string('cargo', 120);
            $table->string('organizacion', 120);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->text('descripcion')->nullable();

            $table->foreign('hoja_vida_id')->references('id')->on('hoja_vida')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiencia');
        Schema::dropIfExists('hoja_vida');
    }
};
