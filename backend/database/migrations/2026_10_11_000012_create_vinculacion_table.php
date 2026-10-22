<?php

use App\Enums\EstadoVinculacion;
use App\Enums\Modalidad;
use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Práctica formal o contratación directa entre un estudiante y una empresa (Tabla 14).
     */
    public function up(): void
    {
        Schema::create('vinculacion', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('estudiante_id');
            $table->integer('empresa_id');
            $table->integer('vacante_id')->nullable();
            $table->integer('tutor_id')->nullable();
            $table->string('tipo', 25);
            $table->smallInteger('semestre_al_vincular');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 12)->default(EstadoVinculacion::Activa->value);
            $table->boolean('reconocida_como_practica')->nullable();
            $table->text('observacion_final')->nullable();

            $table->foreign('estudiante_id')->references('id')->on('estudiante')->cascadeOnDelete();
            $table->foreign('empresa_id')->references('id')->on('empresa')->cascadeOnDelete();
            $table->foreign('vacante_id')->references('id')->on('vacante')->nullOnDelete();
            $table->foreign('tutor_id')->references('id')->on('usuario')->nullOnDelete();
        });
        Restricciones::valoresPermitidos('vinculacion', 'tipo', Modalidad::values());
        Restricciones::valoresPermitidos('vinculacion', 'estado', EstadoVinculacion::values());
    }

    public function down(): void
    {
        Schema::dropIfExists('vinculacion');
    }
};
