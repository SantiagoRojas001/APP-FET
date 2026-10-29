<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitácora de auditoría: quién hizo cada acción relevante y cuándo.
     * Solo se agregan filas; nunca se editan.
     */
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('usuario_id')->nullable();
            $table->string('accion', 60);
            $table->string('entidad', 40);
            $table->integer('entidad_id')->nullable();
            $table->jsonb('detalle')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestampTz('creado_en')->useCurrent();

            $table->foreign('usuario_id')->references('id')->on('usuario')->nullOnDelete();
            $table->index(['entidad', 'entidad_id']);
            $table->index('creado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
