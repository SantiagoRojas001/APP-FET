<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Semestre mínimo desde el cual cada programa autoriza la práctica (Tabla 2).
     */
    public function up(): void
    {
        Schema::create('regla_elegibilidad', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('programa_id');
            $table->smallInteger('semestre_minimo');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();

            $table->foreign('programa_id')->references('id')->on('programa')->cascadeOnDelete();
            $table->index(['programa_id', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regla_elegibilidad');
    }
};
