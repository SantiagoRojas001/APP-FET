<?php

use App\Enums\EstadoVacante;
use App\Enums\Modalidad;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ofertas de práctica o contratación directa publicadas por las empresas (Tabla 12).
     */
    public function up(): void
    {
        Schema::create('vacante', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('empresa_id');
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->string('modalidad', 25);
            $table->string('estado', 10)->default(EstadoVacante::Abierta->value);
            $table->timestampTz('publicada_en')->useCurrent();

            $table->foreign('empresa_id')->references('id')->on('empresa')->cascadeOnDelete();
            $table->index(['estado', 'publicada_en']);
        });
        Restricciones::valoresPermitidos('vacante', 'modalidad', Modalidad::values());
        Restricciones::valoresPermitidos('vacante', 'estado', EstadoVacante::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('vacante');
    }
};
