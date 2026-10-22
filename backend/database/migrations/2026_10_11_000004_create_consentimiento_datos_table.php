<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aceptación de la política de tratamiento de datos personales (Tabla 5).
     */
    public function up(): void
    {
        Schema::create('consentimiento_datos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('usuario_id');
            $table->string('version_politica', 20);
            $table->timestampTz('aceptado_en')->useCurrent();

            $table->foreign('usuario_id')->references('id')->on('usuario')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimiento_datos');
    }
};
