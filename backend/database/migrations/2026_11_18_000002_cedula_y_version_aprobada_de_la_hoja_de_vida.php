<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cédula del estudiante (número, lugar de expedición y fotos) y copia de
     * la hoja de vida aprobada, que las empresas siguen viendo mientras una
     * versión editada espera la revisión docente.
     */
    public function up(): void
    {
        Schema::table('estudiante', function (Blueprint $table) {
            $table->string('lugar_expedicion', 100)->nullable();
        });

        Schema::table('hoja_vida', function (Blueprint $table) {
            $table->string('cedula_frente_url', 255)->nullable();
            $table->string('cedula_reverso_url', 255)->nullable();
            $table->string('cedula_firma_url', 255)->nullable();
            $table->smallInteger('firma_similitud')->nullable();
            $table->jsonb('version_aprobada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hoja_vida', function (Blueprint $table) {
            $table->dropColumn(['cedula_frente_url', 'cedula_reverso_url', 'cedula_firma_url', 'firma_similitud', 'version_aprobada']);
        });

        Schema::table('estudiante', function (Blueprint $table) {
            $table->dropColumn('lugar_expedicion');
        });
    }
};
