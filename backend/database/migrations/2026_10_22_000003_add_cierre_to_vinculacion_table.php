<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos del cierre de la práctica: cuándo completó las horas, quién la
     * finalizó (la empresa al aprobarla o el docente anticipadamente) y el
     * periodo académico en que empezó.
     */
    public function up(): void
    {
        Schema::table('vinculacion', function (Blueprint $table) {
            $table->integer('periodo_id')->nullable();
            $table->timestampTz('horas_cumplidas_en')->nullable();
            $table->integer('finalizada_por')->nullable();

            $table->foreign('periodo_id')->references('id')->on('periodo_academico')->nullOnDelete();
            $table->foreign('finalizada_por')->references('id')->on('usuario')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vinculacion', function (Blueprint $table) {
            $table->dropForeign(['periodo_id']);
            $table->dropForeign(['finalizada_por']);
            $table->dropColumn(['periodo_id', 'horas_cumplidas_en', 'finalizada_por']);
        });
    }
};
