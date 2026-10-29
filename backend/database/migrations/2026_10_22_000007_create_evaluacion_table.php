<?php

use App\Enums\TipoEvaluacion;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuestionarios de cierre: el docente y la empresa evalúan al practicante
     * y el practicante evalúa a la empresa. Una evaluación por tipo.
     */
    public function up(): void
    {
        Schema::create('evaluacion', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vinculacion_id');
            $table->string('tipo', 25);
            $table->integer('evaluador_id');
            $table->jsonb('respuestas');
            $table->timestampTz('creada_en')->useCurrent();

            $table->foreign('vinculacion_id')->references('id')->on('vinculacion')->cascadeOnDelete();
            $table->foreign('evaluador_id')->references('id')->on('usuario');
            $table->unique(['vinculacion_id', 'tipo']);
        });
        Restricciones::valoresPermitidos('evaluacion', 'tipo', TipoEvaluacion::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion');
    }
};
