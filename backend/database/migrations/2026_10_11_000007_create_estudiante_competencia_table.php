<?php

use App\Enums\NivelCompetencia;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Competencias de cada estudiante y su nivel (Tabla 9).
     */
    public function up(): void
    {
        Schema::create('estudiante_competencia', function (Blueprint $table) {
            $table->integer('estudiante_id');
            $table->integer('competencia_id');
            $table->string('nivel', 12);

            $table->primary(['estudiante_id', 'competencia_id']);
            $table->foreign('estudiante_id')->references('id')->on('estudiante')->cascadeOnDelete();
            $table->foreign('competencia_id')->references('id')->on('competencia')->cascadeOnDelete();
        });
        Restricciones::valoresPermitidos('estudiante_competencia', 'nivel', NivelCompetencia::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante_competencia');
    }
};
