<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Periodos académicos (2026-1, 2026-2). Cada vinculación queda en el
     * periodo de su fecha de inicio, para filtrar los informes y cerrar ciclos.
     */
    public function up(): void
    {
        Schema::create('periodo_academico', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 10)->unique();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->timestampTz('cerrado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodo_academico');
    }
};
