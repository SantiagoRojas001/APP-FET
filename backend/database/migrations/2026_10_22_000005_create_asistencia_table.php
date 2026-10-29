<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horas que el estudiante registra por día de práctica.
     */
    public function up(): void
    {
        Schema::create('asistencia', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('vinculacion_id');
            $table->date('fecha');
            $table->decimal('horas', 4, 1);
            $table->string('actividad', 500);
            $table->timestampTz('registrado_en')->useCurrent();

            $table->foreign('vinculacion_id')->references('id')->on('vinculacion')->cascadeOnDelete();
            $table->unique(['vinculacion_id', 'fecha']);
        });
        DB::statement('ALTER TABLE asistencia ADD CONSTRAINT asistencia_horas_check CHECK (horas > 0 AND horas <= 12)');
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia');
    }
};
