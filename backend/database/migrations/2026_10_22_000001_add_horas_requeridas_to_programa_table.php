<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horas de práctica que exige cada programa. La vinculación se da por
     * cumplida cuando las asistencias registradas alcanzan este valor.
     */
    public function up(): void
    {
        Schema::table('programa', function (Blueprint $table) {
            $table->smallInteger('horas_requeridas')->default(480);
        });
    }

    public function down(): void
    {
        Schema::table('programa', function (Blueprint $table) {
            $table->dropColumn('horas_requeridas');
        });
    }
};
