<?php

use App\Enums\EstadoPostulacion;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postulaciones de estudiantes a vacantes (Tabla 13).
     *
     * Ajustes al modelo: la columna `observacion` guarda el motivo que da el
     * docente al rechazar, y un estudiante solo puede postularse una vez a la
     * misma vacante.
     */
    public function up(): void
    {
        Schema::create('postulacion', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vacante_id');
            $table->integer('estudiante_id');
            $table->string('estado', 12)->default(EstadoPostulacion::Enviada->value);
            $table->timestampTz('fecha')->useCurrent();
            $table->text('observacion')->nullable();

            $table->foreign('vacante_id')->references('id')->on('vacante')->cascadeOnDelete();
            $table->foreign('estudiante_id')->references('id')->on('estudiante')->cascadeOnDelete();
            $table->unique(['vacante_id', 'estudiante_id']);
            $table->index('estado');
        });
        Restricciones::valoresPermitidos('postulacion', 'estado', EstadoPostulacion::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('postulacion');
    }
};
