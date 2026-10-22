<?php

use App\Enums\EstadoRevision;
use App\Enums\TipoDocumento;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Controles, diarios de asistencia e informes de avance de una vinculación (Tabla 15).
     */
    public function up(): void
    {
        Schema::create('documento_seguimiento', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vinculacion_id');
            $table->string('tipo', 20);
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->string('archivo_url', 255);
            $table->integer('cargado_por');
            $table->timestampTz('cargado_en')->useCurrent();
            $table->string('estado_revision', 10)->default(EstadoRevision::Pendiente->value);
            $table->text('observaciones')->nullable();

            $table->foreign('vinculacion_id')->references('id')->on('vinculacion')->cascadeOnDelete();
            $table->foreign('cargado_por')->references('id')->on('usuario');
        });
        Restricciones::valoresPermitidos('documento_seguimiento', 'tipo', TipoDocumento::values());
        Restricciones::valoresPermitidos('documento_seguimiento', 'estado_revision', EstadoRevision::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_seguimiento');
    }
};
