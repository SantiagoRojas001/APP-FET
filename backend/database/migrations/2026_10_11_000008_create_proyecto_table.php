<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Proyectos del estudiante con enlace a su repositorio (Tabla 10).
     */
    public function up(): void
    {
        Schema::create('proyecto', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('estudiante_id');
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->string('url_repositorio', 255);
            $table->smallInteger('semestre')->nullable();

            $table->foreign('estudiante_id')->references('id')->on('estudiante')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto');
    }
};
