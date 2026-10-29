<?php

use App\Enums\CategoriaReporte;
use App\Enums\EstadoReporte;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Problemas que reporta cualquier usuario. Si el reporte trata de una
     * práctica, queda ligado a su vinculación.
     */
    public function up(): void
    {
        Schema::create('reporte', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('autor_id');
            $table->integer('vinculacion_id')->nullable();
            $table->string('categoria', 20);
            $table->string('asunto', 150);
            $table->text('descripcion');
            $table->string('estado', 12)->default(EstadoReporte::Abierto->value);
            $table->text('respuesta')->nullable();
            $table->integer('atendido_por')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('atendido_en')->nullable();

            $table->foreign('autor_id')->references('id')->on('usuario')->cascadeOnDelete();
            $table->foreign('vinculacion_id')->references('id')->on('vinculacion')->cascadeOnDelete();
            $table->foreign('atendido_por')->references('id')->on('usuario')->nullOnDelete();
            $table->index(['estado', 'creado_en']);
        });
        Restricciones::valoresPermitidos('reporte', 'categoria', CategoriaReporte::values());
        Restricciones::valoresPermitidos('reporte', 'estado', EstadoReporte::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte');
    }
};
